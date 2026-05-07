@extends('layouts.app')

@section('content')
<div class="w-full max-w-sm mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">

        {{-- Brand --}}
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-4 bg-blue-50 rounded-2xl flex items-center justify-center">
                <i class="bi bi-shield-lock-fill text-primary text-2xl"></i>
            </div>
            <h1 class="text-xl font-black text-slate-900">Enter OTP</h1>
            <p class="text-sm text-slate-400 mt-1">
                We sent a 6-digit code to <span class="font-semibold text-slate-600">{{ $phone }}</span>
            </p>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-check-circle-fill shrink-0"></i> {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('password.verify') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">6-Digit OTP</label>
                <input type="text" name="otp" required autofocus maxlength="6" inputmode="numeric"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-center tracking-[0.5em] font-bold focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('otp') ? 'border-red-400' : '' }}"
                    placeholder="••••••">
                <p class="text-xs text-slate-400 mt-1.5">Valid for 10 minutes.</p>
            </div>
            <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-check-lg"></i> Verify OTP
            </button>
        </form>

        {{-- Resend --}}
        <form action="{{ route('password.send') }}" method="POST" class="mt-4">
            @csrf
            <input type="hidden" name="phone_number" value="{{ $phone }}">
            <button type="submit" class="w-full text-sm text-slate-400 hover:text-primary transition-colors py-2">
                <i class="bi bi-arrow-clockwise"></i> Resend OTP
            </button>
        </form>

        <div class="border-t border-slate-100 mt-4 pt-4 text-center">
            <a href="{{ route('password.request') }}" class="text-sm text-primary font-semibold hover:underline">
                ← Use a different number
            </a>
        </div>

    </div>
</div>
@endsection
