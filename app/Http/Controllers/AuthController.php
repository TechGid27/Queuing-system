<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Department;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const OTP_GUEST_VERIFICATION = 'guest_verification';

    private const OTP_USER_VERIFICATION = 'user_verification';

    private const OTP_PASSWORD_RESET = 'password_reset';

    private SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    // ─── Views ───────────────────────────────────────────────────────────────

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister(Request $request)
    {
        if ($request->integer('department_id')) {
            $department = Department::active()->find($request->integer('department_id'));
            if ($department) {
                $request->session()->put('queue_department_id', $department->id);
            }
        }

        return view('auth.guest_login');
    }

    public function showDepartmentRegister()
    {
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('login')->with('warning', 'The administrator account has already been created.');
        }

        return view('auth.register');
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $key = 'login-attempts:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
        }

        if (Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            RateLimiter::clear($key);

            $user = Auth::guard('web')->user();

            if (! $user->is_active) {
                Auth::guard('web')->logout();

                return back()->withErrors(['email' => 'This account has been deactivated.']);
            }

            if ($user->role === 'staff' && (! $user->department || ! $user->department->is_active)) {
                Auth::guard('web')->logout();

                return back()->withErrors(['email' => 'Your assigned department is currently inactive.']);
            }

            if ($user->phone_number && ! $user->isPhoneVerified()) {
                Auth::guard('web')->logout();
                $request->session()->put([
                    'otp_account_type' => 'user',
                    'otp_phone' => $user->phone_number,
                ]);

                return redirect()->route('student.verify.show')
                    ->with('warning', 'Please verify your phone number first.');
            }

            return in_array($user->role, ['admin', 'staff'], true)
                ? redirect()->intended(route('admin.index'))
                : redirect()->route('login')->withErrors(['email' => 'This account does not have a valid staff role.']);
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ─── Student Registration + OTP ──────────────────────────────────────────
    // Version 2 : Login after registration, OTP verification required for students

    public function registerStudent(Request $request)
    {
        $request->validate([
            'student_id' => 'nullable|string|max:50',
            'phone_number' => 'required|regex:/^09[0-9]{9}$/',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        if ($request->integer('department_id')) {
            $department = Department::active()->find($request->integer('department_id'));
            if (! $department) {
                return back()->withErrors(['department_id' => 'Please select an active department.'])->withInput();
            }

            $request->session()->put('queue_department_id', $department->id);
        }

        Guest::updateOrCreate(
            ['phone_number' => $request->phone_number],
            [
                'student_id' => $request->student_id ?? null,
                'phone_verified_at' => null,
                'role' => 'student',
            ]
        );

        $this->sendOtp($request->phone_number, self::OTP_GUEST_VERIFICATION);
        $request->session()->put([
            'otp_account_type' => 'guest',
            'otp_phone' => $request->phone_number,
        ]);

        return redirect()->route('student.verify.show')
            ->with('success', 'Please enter the OTP sent to your phone.');
    }

    // ─── Temporary Staff Registration (for testing purposes) ─────────────────────────────
    public function registerStaff(Request $request)
    {
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('login')->with('warning', 'The administrator account has already been created.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone_number' => 'required|regex:/^09[0-9]{9}$/|unique:users,phone_number',
            'password' => 'required|min:8|confirmed',
        ]);

        $existingByEmail = User::where('email', $request->email)->first();

        if ($existingByEmail && $existingByEmail->isPhoneVerified()) {
            return back()->withErrors(['email' => 'This email is already registered.'])->withInput();
        }

        User::where('email', $request->email)->whereNull('phone_verified_at')->delete();
        User::where('phone_number', $request->phone_number)->whereNull('phone_verified_at')->delete();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->sendOtp($request->phone_number, self::OTP_USER_VERIFICATION);
        $request->session()->put([
            'otp_account_type' => 'user',
            'otp_phone' => $request->phone_number,
        ]);

        return redirect()->route('student.verify.show')
            ->with('success', 'Account created! Please enter the OTP sent to your phone.');
    }

    // ─── OTP: Show Verify Page ────────────────────────────────────────────────

    public function showVerifyOtp(Request $request)
    {
        $phone = $request->query('phone', $request->session()->get('otp_phone'));
        $accountType = $request->query('account_type', $request->session()->get('otp_account_type', 'guest'));

        if (! $phone || ! in_array($accountType, ['guest', 'user'], true)) {
            return redirect()->route('register');
        }

        $account = ($accountType === 'guest' ? Guest::query() : User::query())
            ->where('phone_number', $phone)
            ->whereNull('phone_verified_at')
            ->first();

        if (! $account) {
            return redirect()->route($accountType === 'guest' ? 'register' : 'private_register')
                ->with('warning', 'No pending verification found for that number.');
        }

        $request->session()->put([
            'otp_account_type' => $accountType,
            'otp_phone' => $phone,
        ]);

        return view('auth.verify-otp', compact('phone', 'accountType'));
    }

    // ─── OTP: Verify ─────────────────────────────────────────────────────────

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^09[0-9]{9}$/',
            'account_type' => 'required|in:guest,user',
            'otp' => 'required|digits:6',
        ]);

        $purpose = $request->account_type === 'guest'
            ? self::OTP_GUEST_VERIFICATION
            : self::OTP_USER_VERIFICATION;
        $attemptKey = "otp-verify:{$request->account_type}:{$request->phone}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            $seconds = RateLimiter::availableIn($attemptKey);

            return back()->withErrors(['otp' => "Too many verification attempts. Try again in {$seconds} seconds."]);
        }

        $record = PhoneOtp::where('phone_number', $request->phone)
            ->where('purpose', $purpose)
            ->orderBy('id', 'desc')
            ->first();

        if (! $record || $record->otp !== $request->otp) {
            RateLimiter::hit($attemptKey, 600);

            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        if ($record->isExpired()) {
            $record->delete();

            return back()->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        $account = ($request->account_type === 'guest' ? Guest::query() : User::query())
            ->where('phone_number', $request->phone)
            ->whereNull('phone_verified_at')
            ->first();

        if (! $account) {
            return redirect()->route($request->account_type === 'guest' ? 'register' : 'private_register')
                ->withErrors(['phone' => 'Account not found.']);
        }

        $account->update(['phone_verified_at' => now()]);
        PhoneOtp::where('phone_number', $request->phone)->where('purpose', $purpose)->delete();
        RateLimiter::clear($attemptKey);
        $request->session()->forget(['otp_account_type', 'otp_phone']);

        if ($request->account_type === 'guest') {
            Auth::guard('student')->login($account);
            Auth::shouldUse('student');
            $request->session()->regenerate();
            $departmentId = $request->session()->pull('queue_department_id');

            return redirect()->route('student.index', array_filter(['department_id' => $departmentId]))
                ->with('success', 'Phone verified. Welcome to the queue!');
        }

        Auth::guard('web')->login($account);
        $request->session()->regenerate();

        return redirect()->route('admin.index')->with('success', 'Phone verified. Welcome, '.$account->name.'!');
    }

    // ─── OTP: Resend ─────────────────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^09[0-9]{9}$/',
            'account_type' => 'required|in:guest,user',
        ]);

        $account = ($request->account_type === 'guest' ? Guest::query() : User::query())
            ->where('phone_number', $request->phone)
            ->whereNull('phone_verified_at')
            ->first();

        if (! $account) {
            return back()->withErrors(['phone' => 'No pending verification found for this number.']);
        }

        $key = "otp-resend:{$request->account_type}:{$request->phone}";

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['phone' => "Too many OTP requests. Try again in {$seconds} seconds."]);
        }

        RateLimiter::hit($key, 120);
        $purpose = $request->account_type === 'guest'
            ? self::OTP_GUEST_VERIFICATION
            : self::OTP_USER_VERIFICATION;
        $this->sendOtp($request->phone, $purpose);
        $request->session()->put([
            'otp_account_type' => $request->account_type,
            'otp_phone' => $request->phone,
        ]);

        return back()->with('success', 'A new OTP has been sent to your phone.');
    }

    // ─── Forgot Password ─────────────────────────────────────────────────────

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|regex:/^09[0-9]{9}$/',
        ]);

        $user = User::where('phone_number', $request->phone_number)
            ->whereNotNull('phone_verified_at')
            ->first();

        // Always show success to prevent phone enumeration
        if (! $user) {
            return back()->with('success', 'If that number is registered, an OTP has been sent.');
        }

        $key = 'reset-otp:'.$request->phone_number;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['phone_number' => "Too many attempts. Try again in {$seconds} seconds."]);
        }
        RateLimiter::hit($key, 120);

        $this->dispatchResetOtp($request->phone_number);

        return redirect()->route('password.verify.show', ['phone' => $request->phone_number])
            ->with('success', 'OTP sent! Enter it below to reset your password.');
    }

    public function showResetOtp(Request $request)
    {
        $phone = $request->query('phone');
        if (! $phone) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-otp', compact('phone'));
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'otp' => 'required|digits:6',
        ]);

        $record = PhoneOtp::where('phone_number', $request->phone)
            ->where('purpose', self::OTP_PASSWORD_RESET)
            ->orderBy('id', 'desc')
            ->first();

        if (! $record || $record->otp !== $request->otp) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        if ($record->isExpired()) {
            return back()->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        // Store verified phone in session, delete OTP
        session(['reset_verified_phone' => $request->phone]);
        PhoneOtp::where('phone_number', $request->phone)
            ->where('purpose', self::OTP_PASSWORD_RESET)
            ->delete();

        return redirect()->route('password.reset.show');
    }

    public function showResetPassword()
    {
        if (! session('reset_verified_phone')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $phone = session('reset_verified_phone');
        if (! $phone) {
            return redirect()->route('password.request')
                ->withErrors(['phone' => 'Session expired. Please start over.']);
        }

        $user = User::where('phone_number', $phone)->first();
        if (! $user) {
            return redirect()->route('password.request')
                ->withErrors(['phone' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->password)]);
        session()->forget('reset_verified_phone');

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $redirect = in_array($user->role, ['admin', 'staff'], true) ? route('admin.index') : route('login');

        return redirect($redirect)->with('success', 'Password reset successfully. Welcome back, '.$user->name.'!');
    }

    // ─── OTP Helper ──────────────────────────────────────────────────────────

    private function sendOtp(string $phone, string $purpose): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        PhoneOtp::where('phone_number', $phone)->where('purpose', $purpose)->delete();
        PhoneOtp::create([
            'phone_number' => $phone,
            'purpose' => $purpose,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->sms->sendOtp($phone, $otp);
    }

    private function dispatchResetOtp(string $phone): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        PhoneOtp::where('phone_number', $phone)->where('purpose', self::OTP_PASSWORD_RESET)->delete();
        PhoneOtp::create([
            'phone_number' => $phone,
            'purpose' => self::OTP_PASSWORD_RESET,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->sms->sendPasswordResetOtp($phone, $otp);
    }
}
