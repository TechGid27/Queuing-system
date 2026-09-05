<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PurposeController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// ─── Public Queue Display ─────────────────────────────────────────────────────
Route::get('/', [QueueController::class, 'index'])->name('home');
Route::get('/tv', [QueueController::class, 'tv'])->name('tv');
Route::get('/api/queue-status', [QueueController::class, 'getStatus'])->name('api.queueStatus');
Route::get('/api/purposes', [PurposeController::class, 'getActivePurposes'])->name('api.purposes');

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest:web,student')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('recaptcha')->name('login.post');

    // Guest or Student Registration
    Route::get('/verify', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/verify', [AuthController::class, 'registerStudent'])->middleware('recaptcha')->name('register.post');

    // One-time initial administrator registration.
    Route::get('/private/register', [AuthController::class, 'showDepartmentRegister'])->name('private_register');
    Route::post('/private/register', [AuthController::class, 'registerStaff'])->middleware('recaptcha')->name('private_register.post');

    // OTP Verification
    Route::get('/verify-otp', [AuthController::class, 'showVerifyOtp'])->name('student.verify.show');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('student.verify');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->name('student.resend.otp');

    // ─── Forgot / Reset Password (via SMS OTP) ────────────────────────────────
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->name('password.send');
    Route::get('/reset-otp', [AuthController::class, 'showResetOtp'])->name('password.verify.show');
    Route::post('/reset-otp', [AuthController::class, 'verifyResetOtp'])->name('password.verify');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset.show');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ─── Student Portal (auth required) ──────────────────────────────────────────
Route::middleware(['auth:student', 'is_student'])->group(function () {
    Route::get('/student/index', [QueueController::class, 'index'])->name('student.index');
    Route::post('/queue', [QueueController::class, 'store'])->name('queue.store');
});

// ─── Staff Portal (auth required) ────────────────────────────────────────────
Route::middleware(['auth:web', 'is_staff'])->group(function () {
    Route::get('/admin', [StaffController::class, 'index'])->name('admin.index');
    Route::post('/admin/call-next', [StaffController::class, 'callNext'])->name('admin.callNext');
    Route::post('/admin/reject/{id}', [StaffController::class, 'reject'])->name('admin.reject');
    Route::post('/admin/accept/{id}', [StaffController::class, 'complete'])->name('admin.complete');
    Route::get('/admin/reports', [StaffController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/reports/download', [StaffController::class, 'downloadReport'])->name('admin.reports.download');

    Route::get('/admin/waiting-list', [StaffController::class, 'waitingList'])->name('admin.waitingList');
    Route::post('/admin/toggle-pause', [StaffController::class, 'togglePause'])->name('admin.togglePause');

});

// ─── Administrator-only management ───────────────────────────────────────────
Route::middleware(['auth:web', 'is_admin'])->group(function () {
    Route::get('/admin/departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
    Route::post('/admin/departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
    Route::patch('/admin/departments/{department}/status', [DepartmentController::class, 'updateStatus'])->name('admin.departments.status');
    Route::post('/admin/staff', [DepartmentController::class, 'storeStaff'])->name('admin.staff.store');
    Route::patch('/admin/staff/{staff}/status', [DepartmentController::class, 'updateStaffStatus'])->name('admin.staff.status');

    Route::resource('/admin/purposes', PurposeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index' => 'admin.purposes.index',
            'store' => 'admin.purposes.store',
            'update' => 'admin.purposes.update',
            'destroy' => 'admin.purposes.destroy',
        ]);
});
