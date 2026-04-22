<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\PurposeController;

// ─── Public Queue Display ─────────────────────────────────────────────────────
Route::get('/', [QueueController::class, 'index'])->name('home');
Route::get('/api/queue-status', [QueueController::class, 'getStatus'])->name('api.queueStatus');

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'registerStudent'])->name('register.post');

// OTP Verification
Route::get('/verify-otp', [AuthController::class, 'showVerifyOtp'])->name('student.verify.show');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('student.verify');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->name('student.resend.otp');

// ─── Student Portal (auth required) ──────────────────────────────────────────
Route::middleware(['auth', 'is_student'])->group(function () {
    Route::get('/student/index', [QueueController::class, 'index'])->name('student.index');
    Route::post('/queue', [QueueController::class, 'store'])->name('queue.store');
});

// ─── Staff Portal (auth required) ────────────────────────────────────────────
Route::middleware(['auth', 'is_staff'])->group(function () {
    Route::get('/admin', [StaffController::class, 'index'])->name('admin.index');
    Route::post('/admin/call-next', [StaffController::class, 'callNext'])->name('admin.callNext');
    Route::post('/admin/reject/{id}', [StaffController::class, 'reject'])->name('admin.reject');
    Route::post('/admin/accept/{id}', [StaffController::class, 'complete'])->name('admin.complete');
    Route::get('/admin/reports', [StaffController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/reports/download', [StaffController::class, 'downloadReport'])->name('admin.reports.download');

    // Purpose Management
    Route::resource('/admin/purposes', PurposeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index'   => 'admin.purposes.index',
            'store'   => 'admin.purposes.store',
            'update'  => 'admin.purposes.update',
            'destroy' => 'admin.purposes.destroy',
        ]);
});
