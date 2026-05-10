@extends('layouts.app')
@section('page-title', 'Reset Password')
@section('content')
<div class="w-full max-w-sm mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">

        {{-- Brand --}}
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-4 bg-green-50 rounded-2xl flex items-center justify-center">
                <i class="bi bi-key-fill text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-xl font-black text-slate-900">Set New Password</h1>
            <p class="text-sm text-slate-400 mt-1">Choose a strong password for your account.</p>
        </div>

        {{-- Alerts --}}
        @if($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('password.reset') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">New Password</label>
                <input type="password" name="password" required autofocus minlength="8"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('password') ? 'border-red-400' : '' }}"
                    placeholder="Minimum 8 characters">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirm New Password</label>
                <input type="password" name="password_confirmation" required minlength="8"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
                    placeholder="Re-enter your password">
            </div>
            <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-check-circle-fill"></i> Reset Password
            </button>
        </form>

    </div>
</div>
@endsection
