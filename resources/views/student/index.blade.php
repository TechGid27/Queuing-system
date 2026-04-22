@extends('layouts.app')
@section('page-title', 'Live Queue Status')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Left: Live Numbers --}}
    <div class="lg:col-span-3 flex flex-col gap-4">

        {{-- Now Serving --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-8 text-center">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Now Serving</div>
            <div class="ticket-xl text-primary" id="current-number">{{ $currentNumber }}</div>
            <div class="mt-4">
                <span class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                </span>
            </div>
        </div>

        {{-- Next in Line --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Next in Line</div>
            <div class="ticket-lg text-slate-400" id="next-number">{{ $nextNumber }}</div>
            <div class="text-xs text-slate-400 mt-2">Please prepare your requirements</div>
        </div>

        {{-- Estimated Wait Time --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3 text-center">Queue Status</div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-yellow-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-black text-yellow-600" id="waiting-count">{{ $waitingCount }}</div>
                    <div class="text-[11px] font-semibold text-yellow-500 mt-0.5">Students Waiting</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-black text-blue-600" id="est-wait-time">
                        @if($waitingCount > 0)
                            ~{{ $waitingCount * 5 }} min
                        @else
                            --
                        @endif
                    </div>
                    <div class="text-[11px] font-semibold text-blue-400 mt-0.5">Est. Wait (avg 5 min)</div>
                </div>
            </div>
        </div>

    </div>

    {{-- Right: Ticket Card --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 lg:p-6">

        @if($myTicket)
            {{-- Active ticket --}}
            <div class="text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-5">Your Virtual Ticket</div>

                <div class="bg-slate-50 rounded-xl border-2 border-dashed border-slate-200 py-7 px-4 mb-5">
                    <div class="text-xs text-slate-400 mb-2">Ticket Number</div>
                    <div class="ticket-lg text-primary" id="my-ticket-number">{{ $myTicket->ticket_number }}</div>
                    <div class="mt-4">
                        <span id="my-ticket-status"
                            class="inline-flex items-center gap-1.5 text-xs font-bold px-4 py-1.5 rounded-full
                            {{ $myTicket->status === 'serving' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $myTicket->status === 'serving' ? '🟢 YOUR TURN NOW!' : '⏳ WAITING' }}
                        </span>
                    </div>
                </div>

                <div class="text-sm text-slate-500 mb-5">{{ $myTicket->purpose }}</div>

                <div class="flex flex-col gap-2">
                    <button onclick="window.print()"
                        class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-600 text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        <i class="bi bi-printer"></i> Print Ticket
                    </button>
                    <a href="{{ route('student.index') }}"
                        class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-600 text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </a>
                </div>

                <p class="text-xs text-slate-400 mt-4 leading-relaxed">
                    Stay nearby. You'll receive an SMS when it's your turn.
                </p>
            </div>

        @else
            {{-- Join Queue Form --}}
            <h3 class="text-base font-bold text-slate-800 mb-1">Get Your Ticket</h3>
            <p class="text-sm text-slate-400 mb-5">Save your spot without standing in line.</p>

            <form action="{{ route('queue.store') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Purpose of Visit</label>
                    <select name="purpose_id" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition
                               {{ $errors->has('purpose_id') ? 'border-red-400' : '' }}">
                        <option value="">Choose one...</option>
                        @foreach($purposes as $purpose)
                            <option value="{{ $purpose->id }}">{{ $purpose->name }}</option>
                        @endforeach
                    </select>
                    @error('purpose_id')
                        <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors mt-2">
                    <i class="bi bi-ticket-perforated-fill"></i> Join Virtual Queue
                </button>
                <p class="text-center text-xs text-slate-400 mt-3">A virtual ticket will be generated instantly.</p>
            </form>
        @endif

    </div>

</div>
@endsection

@section('scripts')
<script>
if (window.PUSHER_APP_KEY) {
    const pusher = new Pusher(window.PUSHER_APP_KEY, {
        cluster: window.PUSHER_APP_CLUSTER || 'mt1',
        forceTLS: window.PUSHER_SCHEME === 'https',
    });

    pusher.subscribe('queue').bind('queue.updated', function(data) {
        const currentEl = document.getElementById('current-number');
        const nextEl    = document.getElementById('next-number');
        const waitEl    = document.getElementById('waiting-count');
        const estEl     = document.getElementById('est-wait-time');

        if (currentEl && data.current && currentEl.innerText !== data.current) {
            currentEl.style.opacity = '0.3';
            currentEl.style.transition = 'opacity .2s';
            setTimeout(() => { currentEl.innerText = data.current; currentEl.style.opacity = '1'; }, 200);
        }
        if (nextEl && data.next) nextEl.innerText = data.next;
        if (waitEl && data.waiting_count !== undefined) waitEl.innerText = data.waiting_count;
        if (estEl && data.waiting_count !== undefined) estEl.innerText = data.waiting_count > 0 ? '~' + (data.waiting_count * 5) + ' min' : '--';

        // Real-time ticket status update
        const myTicketEl = document.getElementById('my-ticket-number');
        const myStatusEl = document.getElementById('my-ticket-status');
        if (myTicketEl && myStatusEl && data.current === myTicketEl.innerText.trim()) {
            myStatusEl.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-4 py-1.5 rounded-full bg-green-100 text-green-700';
            myStatusEl.innerText = '🟢 YOUR TURN NOW!';
            showToast("🔔 It's your turn! Please proceed to the window.", 'success');
        }
    });
} else {
    setInterval(() => {
        fetch('{{ route("api.queueStatus") }}').then(r => r.json()).then(data => {
            const c = document.getElementById('current-number');
            const n = document.getElementById('next-number');
            if (c && data.current) c.innerText = data.current;
            if (n && data.next) n.innerText = data.next;
        });
    }, 5000);
}
</script>
<style>
    @media print {
        #sidebar, header { display: none !important; }
        .lg\:ml-60 { margin-left: 0 !important; }
    }
</style>
@endsection
