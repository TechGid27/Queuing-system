@extends('layouts.app')
@section('page-title', 'Register')
@section('content')
<div class="w-full max-w-md mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">

        {{-- Icon --}}
        <div class="w-16 h-16 mx-auto mb-5 flex items-center justify-center">
            <img src="/1973802-removebg-preview.png" alt="ACLC Logo" class="w-full h-full object-contain">
        </div>

        {{-- Header --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-black text-slate-900">Verify & Get Ticket</h1>
            <p class="text-sm text-slate-400 mt-1">Please verify your information to get your ticket</p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('register.post') }}" method="POST" class="space-y-4" id="register-form">
            @csrf
            @if(session('queue_department_id'))
                <input type="hidden" name="department_id" value="{{ session('queue_department_id') }}">
            @endif

            {{-- <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('name') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="Enter your full name">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div> --}}

            {{-- <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('email') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="yourname@example.com">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div> --}}

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Student ID <span class="text-gray-400">(Optional)</span></label>
                <input type="text" name="student_id" value="{{ old('student_id') }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('student_id') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="Enter your Student ID">
                @error('student_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number') }}" required
                    maxlength="11" inputmode="numeric" autocomplete="tel"
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('phone_number') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="09xxxxxxxxx">
                <p class="text-xs text-slate-400 mt-1">
                    <i class="bi bi-info-circle"></i> Used for SMS notifications & OTP verification
                </p>
                <p class="text-red-500 text-xs mt-1 hidden" id="phone-error">Must start with 09 and be exactly 11 digits.</p>
                @error('phone_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3.5 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('password') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="Minimum 8 characters">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div> --}}

            {{-- <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirm Password</label>
                <input type="password" name="password_confirmation" required
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
                    placeholder="••••••••">
            </div> --}}

            {{-- reCAPTCHA v2 checkbox --}}
            @if(config('services.recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
            </div>
            @error('recaptcha')
                <p class="text-red-500 text-xs text-center -mt-2">{{ $message }}</p>
            @enderror
            @endif

            <button type="submit" id="register-btn"
                class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-send-fill"></i> Verify Phone & Login
            </button>
        </form>

        <div class="border-t border-slate-100 mt-6 pt-5 text-center">
            {{-- <p class="text-sm text-slate-400">
                Already have an account?
                <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Login here</a>
            </p> --}}
            <div class="text-end mt-4">
                <a href="{{ route('home', array_filter(['department_id' => session('queue_department_id')])) }}" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">
                    ← Back to Home
                </a>
            </div>
        </div>
   

        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    const phoneInput = document.getElementById('phone_number');
    const phoneError = document.getElementById('phone-error');

    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    phoneInput.addEventListener('blur', function () {
        const val   = this.value;
        const valid = /^09[0-9]{9}$/.test(val);
        phoneError.classList.toggle('hidden', valid || val === '');
        this.classList.toggle('border-red-400', !valid && val !== '');
        this.classList.toggle('border-slate-200', valid || val === '');
    });

    // Block form submit if phone invalid
    document.getElementById('register-form').addEventListener('submit', function (e) {
        const val = phoneInput.value;
        if (!/^09[0-9]{9}$/.test(val)) {
            e.preventDefault();
            phoneError.classList.remove('hidden');
            phoneInput.classList.add('border-red-400');
            phoneInput.focus();
        }
    });
</script>

@if(config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
@endsection
