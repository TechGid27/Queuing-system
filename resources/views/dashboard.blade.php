@extends('layouts.app')

@section('content')
{{-- Fix #2: Full-width guest layout, not squeezed into auth-wrap --}}
<div class="w-full max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2 bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-full mb-4">
            <span class="w-1.5 h-1.5 bg-white rounded-full badge-live"></span> LIVE QUEUE STATUS
        </div>
        <h1 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">ACLC Mandaue Registrar</h1>
        <p class="text-slate-400 text-sm mt-1">Virtual Queue System</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

        {{-- Live Numbers --}}
        <div class="md:col-span-3 flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-10 text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Now Serving</div>
                <div class="ticket-xl text-primary" id="current-number">{{ $currentNumber }}</div>
                <div class="mt-4">
                    <span class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Next in Line</div>
                <div class="ticket-lg text-slate-400" id="next-number">{{ $nextNumber }}</div>
                <div class="text-xs text-slate-400 mt-2">Please prepare your requirements</div>
            </div>
            {{-- Queue Status --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3 text-center">Queue Status</div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-yellow-50 rounded-xl p-3 text-center">
                        <div class="text-2xl font-black text-yellow-600" id="waiting-count">{{ $waitingCount }}</div>
                        <div class="text-[11px] font-semibold text-yellow-500 mt-0.5">Students Waiting</div>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <div class="text-2xl font-black text-blue-600" id="est-wait-time">
                            {{ $waitingCount > 0 ? '~' . ($waitingCount * 5) . ' min' : '--' }}
                        </div>
                        <div class="text-[11px] font-semibold text-blue-400 mt-0.5">Est. Wait (avg 5 min)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="md:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 flex flex-col">
            <div class="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-white text-2xl mx-auto mb-4">
                <i class="bi bi-ticket-perforated-fill"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-800 text-center mb-2">Virtual Ticketing</h2>
            <p class="text-sm text-slate-400 text-center mb-6 leading-relaxed">
                Skip the line. Get a virtual ticket and we'll notify you via SMS when it's your turn.
            </p>
            <div class="flex flex-col gap-2 mt-auto">
                <a href="{{ route('login') }}"
                    class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors">
                    <i class="bi bi-box-arrow-in-right"></i> Login to Get a Ticket
                </a>
                <a href="{{ route('register') }}"
                    class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-600 font-semibold text-sm px-4 py-3 rounded-xl hover:bg-slate-50 transition-colors">
                    <i class="bi bi-person-plus"></i> Create Account
                </a>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
if (window.PUSHER_APP_KEY) {
    const pusher = new Pusher(window.PUSHER_APP_KEY, { cluster: window.PUSHER_APP_CLUSTER || 'mt1', forceTLS: true });
    pusher.subscribe('queue').bind('queue.updated', function(data) {
        const c = document.getElementById('current-number');
        const n = document.getElementById('next-number');
        const w = document.getElementById('waiting-count');
        const e = document.getElementById('est-wait-time');
        if (c && data.current) { c.style.opacity='.3'; c.style.transition='opacity .2s'; setTimeout(()=>{c.innerText=data.current;c.style.opacity='1';},200); }
        if (n && data.next) n.innerText = data.next;
        if (w && data.waiting_count !== undefined) w.innerText = data.waiting_count;
        if (e && data.waiting_count !== undefined) e.innerText = data.waiting_count > 0 ? '~' + (data.waiting_count * 5) + ' min' : '--';
    });
} else {
    setInterval(() => {
        fetch('{{ route("api.queueStatus") }}').then(r=>r.json()).then(data=>{
            const c=document.getElementById('current-number');
            const n=document.getElementById('next-number');
            if(c&&data.current) c.innerText=data.current;
            if(n&&data.next) n.innerText=data.next;
        });
    }, 5000);
}
</script>
@endsection
