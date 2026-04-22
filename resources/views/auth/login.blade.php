@extends('layouts.app')

@section('content')
<div class="w-full max-w-sm mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">

        {{-- Brand --}}
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-white text-xl mx-auto mb-4">
                <i class="bi bi-ticket-perforated-fill"></i>
            </div>
            <h1 class="text-xl font-black text-slate-900">ACLC Queue System</h1>
            <p class="text-sm text-slate-400 mt-1">Registrar's Office — Mandaue</p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first() }}
            </div>
        @endif

        @if(session('warning'))
            <div class="flex items-start gap-2 bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-exclamation-triangle-fill mt-0.5 shrink-0"></i> {{ session('warning') }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                        {{ $errors->has('email') ? 'border-red-400' : '' }}"
                    placeholder="yourname@example.com">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
                    placeholder="••••••••">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="remember" name="remember" class="w-4 h-4 accent-primary rounded">
                <label for="remember" class="text-sm text-slate-500 cursor-pointer">Keep me logged in</label>
            </div>
            <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>

        <div class="border-t border-slate-100 mt-6 pt-5 text-center">
            <p class="text-sm text-slate-400">
                New student?
                <a href="{{ route('register') }}" class="text-primary font-semibold hover:underline">Create an account</a>
            </p>
        </div>

    </div>
</div>
@endsection
