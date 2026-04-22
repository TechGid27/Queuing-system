@extends('layouts.app')

@section('content')
<div class="w-full max-w-sm mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 lg:p-8">
        <div class="w-12 h-12 rounded-2xl bg-green-600 flex items-center justify-center text-white text-xl mx-auto mb-5">
            <i class="bi bi-phone-fill"></i>
        </div>
        <div class="text-center mb-6">
            <h1 class="text-xl font-black text-slate-900">Verify Your Phone</h1>
            <p class="text-sm text-slate-400 mt-1">We sent a 6-digit code to</p>
            <p class="text-base font-bold text-slate-800 mt-1">{{ $phone }}</p>
        </div>

        @if(session('success'))
            <div class="flex items-start gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-check-circle-fill mt-0.5 shrink-0"></i> {{ session('success') }}
            </div>
        @endif
        @if($errors->has('otp'))
            <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $errors->first('otp') }}
            </div>
        @endif

        <form action="{{ route('student.verify') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}">
            <div>
                <label class="block text-xs font-semibold text-slate-600 text-center mb-2">Enter OTP Code</label>
                <input type="text" name="otp" id="otp" required
                    maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="w-full px-4 py-4 rounded-xl border text-center text-3xl font-black tracking-[1rem] focus:outline-none focus:ring-2 focus:ring-green-500/30 focus:border-green-500 transition {{ $errors->has('otp') ? 'border-red-400' : 'border-slate-200' }}"
                    placeholder="000000">
            </div>
            <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                <i class="bi bi-check-circle-fill"></i> Verify & Continue
            </button>
        </form>

        <div class="border-t border-slate-100 mt-5 pt-5">
            <form action="{{ route('student.resend.otp') }}" method="POST">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                @error('phone')
                    <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-3">
                        <i class="bi bi-x-circle-fill mt-0.5 shrink-0"></i> {{ $message }}
                    </div>
                @enderror
                <button type="submit" id="resend-btn"
                    class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-600 font-semibold text-sm px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                    <i class="bi bi-arrow-clockwise"></i> Resend OTP
                </button>
            </form>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('register') }}" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">
                ← Back to Registration
            </a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('otp').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    const resendBtn = document.getElementById('resend-btn');
    let countdown = 60;

    function startCooldown() {
        resendBtn.disabled = true;
        resendBtn.classList.add('opacity-50', 'cursor-not-allowed');
        const interval = setInterval(() => {
            resendBtn.innerHTML = `<i class="bi bi-clock"></i> Resend in ${countdown}s`;
            countdown--;
            if (countdown < 0) {
                clearInterval(interval);
                resendBtn.disabled = false;
                resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                resendBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resend OTP';
            }
        }, 1000);
    }

    startCooldown();
    resendBtn.addEventListener('click', () => { countdown = 60; });
</script>
@endsection
