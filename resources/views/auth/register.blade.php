@extends('layouts.app')

@section('content')
<div class="w-full max-w-md mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">

        {{-- Icon --}}
        <div class="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-white text-xl mx-auto mb-5">
            <i class="bi bi-person-plus-fill"></i>
        </div>

        {{-- Header --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-black text-slate-900">Create Account</h1>
            <p class="text-sm text-slate-400 mt-1">Register to join the virtual queue</p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('register.post') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('name') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="Enter your full name">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('email') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="yourname@example.com">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Phone Number</label>
                <input type="text" name="phone_number" value="{{ old('phone_number') }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('phone_number') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="09xxxxxxxxx">
                <p class="text-xs text-slate-400 mt-1">
                    <i class="bi bi-info-circle"></i> Used for SMS notifications & OTP verification
                </p>
                @error('phone_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('password') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="Minimum 8 characters">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirm Password</label>
                <input type="password" name="password_confirmation" required
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
                    placeholder="••••••••">
            </div>

            <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-send-fill"></i> Register & Verify Phone
            </button>
        </form>

        <div class="border-t border-slate-100 mt-6 pt-5 text-center">
            <p class="text-sm text-slate-400">
                Already have an account?
                <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Login here</a>
            </p>
        </div>

    </div>
</div>
@endsection
