<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PhoneOtp;
use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
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

    public function showRegister()
    {
        return view('auth.register');
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $key = 'login-attempts:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            RateLimiter::clear($key);

            $user = Auth::user();

            // Block unverified students
            if ($user->role === 'student' && ! $user->isPhoneVerified()) {
                Auth::logout();
                return redirect()->route('student.verify.show', ['phone' => $user->phone_number])
                    ->with('warning', 'Please verify your phone number first.');
            }

            return $user->role === 'staff'
                ? redirect()->intended(route('admin.index'))
                : redirect()->intended(route('student.index'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ─── Student Registration + OTP ──────────────────────────────────────────

    public function registerStudent(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users',
            'phone_number' => 'required|regex:/^09[0-9]{9}$/|unique:users,phone_number',
            'password'     => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'phone_number' => $request->phone_number,
            'password'     => Hash::make($request->password),
            'role'         => 'student',
        ]);

        $this->sendOtp($request->phone_number);

        return redirect()->route('student.verify.show', ['phone' => $request->phone_number])
            ->with('success', 'Account created! Please enter the OTP sent to your phone.');
    }

    // ─── OTP: Show Verify Page ────────────────────────────────────────────────

    public function showVerifyOtp(Request $request)
    {
        $phone = $request->query('phone');

        if (! $phone) {
            return redirect()->route('register');
        }

        $user = User::where('phone_number', $phone)
            ->whereNull('phone_verified_at')
            ->first();

        if (! $user) {
            return redirect()->route('register')
                ->with('warning', 'No pending verification found for that number.');
        }

        return view('auth.verify-otp', compact('phone'));
    }

    // ─── OTP: Verify ─────────────────────────────────────────────────────────

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'otp'   => 'required|digits:6',
        ]);

        $record = PhoneOtp::where('phone_number', $request->phone)
            ->orderBy('id', 'desc')
            ->first();

        if (! $record || $record->otp !== $request->otp) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        if ($record->isExpired()) {
            return back()->withErrors(['otp' => 'OTP has expired. Please request a new one.']);
        }

        $user = User::where('phone_number', $request->phone)->first();

        if (! $user) {
            return redirect()->route('register')->withErrors(['phone' => 'Account not found.']);
        }

        $user->update(['phone_verified_at' => now()]);
        PhoneOtp::where('phone_number', $request->phone)->delete();

        Auth::login($user);

        return redirect()->route('student.index')->with('success', 'Phone verified! Welcome, ' . $user->name . '!');
    }

    // ─── OTP: Resend ─────────────────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^09[0-9]{9}$/',
        ]);

        $key = 'otp-resend:' . $request->phone;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['phone' => "Too many OTP requests. Try again in {$seconds} seconds."]);
        }

        RateLimiter::hit($key, 120);
        $this->sendOtp($request->phone);

        return back()->with('success', 'A new OTP has been sent to your phone.');
    }

    // ─── OTP Helper ──────────────────────────────────────────────────────────

    private function sendOtp(string $phone): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneOtp::where('phone_number', $phone)->delete();

        PhoneOtp::create([
            'phone_number' => $phone,
            'otp'          => $otp,
            'expires_at'   => now()->addMinutes(10),
        ]);

        $this->sms->sendOtp($phone, $otp);
    }
}
