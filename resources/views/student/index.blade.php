@extends('layouts.app')
@section('page-title', 'Live Queue Status')

@section('content')

{{-- Queue Paused Banner --}}
<div id="pause-banner" class="{{ $queuePaused ? '' : 'hidden' }}">
    <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 text-yellow-800 px-5 py-4 rounded-2xl mb-4">
        <i class="bi bi-pause-circle-fill text-yellow-500 text-xl shrink-0"></i>
        <div>
            <div class="font-bold text-sm">Queue is currently on break</div>
            <div class="text-xs text-yellow-600 mt-0.5">The Cashier's office is temporarily unavailable. Please wait — the queue will resume shortly.</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Left: Live Numbers --}}
    <div class="lg:col-span-3 flex flex-col gap-4">

        {{-- Now Serving --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-8 text-center">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Now Serving</div>
            <div class="ticket-xl {{ $queuePaused ? 'text-slate-300' : 'text-primary' }}" id="current-number">{{ $currentNumber }}</div>
            <div class="mt-4">
                @if($queuePaused)
                    <span id="break-badge" class="inline-flex items-center gap-1.5 bg-yellow-50 text-yellow-700 text-xs font-bold px-3 py-1.5 rounded-full">
                        <i class="bi bi-pause-fill"></i> ON BREAK
                    </span>
                    <span id="live-badge" class="hidden badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                    </span>
                @else
                    <span id="break-badge" class="hidden inline-flex items-center gap-1.5 bg-yellow-50 text-yellow-700 text-xs font-bold px-3 py-1.5 rounded-full">
                        <i class="bi bi-pause-fill"></i> ON BREAK
                    </span>
                    <span id="live-badge" class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                    </span>
                @endif
            </div>
        </div>

        {{-- Next in Line --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Next in Line</div>
            <div class="ticket-lg text-slate-400" id="next-number">{{ $nextNumber }}</div>
            <div class="text-xs text-slate-400 mt-2">Please prepare your requirements</div>
        </div>

        {{-- Queue Status + Est. Wait --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3 text-center">Queue Status</div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-yellow-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-black text-yellow-600" id="waiting-count">{{ $waitingCount }}</div>
                    <div class="text-[11px] font-semibold text-yellow-500 mt-0.5">Students Waiting</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    @php
                        $myPos = $myPosition ?? null;
                        $posForCalc = $myPos ?? $waitingCount;
                        $estMins = $posForCalc > 0 ? round($posForCalc * $avgServeTime) : 0;
                    @endphp
                    <div class="text-2xl font-black text-blue-600" id="est-wait-time">
                        @if($queuePaused)
                            On Break
                        @elseif($posForCalc > 0)
                            ~{{ $estMins }} min
                        @else
                            Ready
                        @endif
                    </div>
                    <div class="text-[11px] font-semibold text-blue-400 mt-0.5" id="est-wait-label">
                        @if($myPos)
                            Your est. wait (pos. #{{ $myPos }})
                        @else
                            Est. Wait (avg {{ $avgServeTime }} min/student)
                        @endif
                    </div>
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
                    @if($myPosition && $myTicket->status === 'waiting')
                    <div class="mt-3 text-xs text-slate-400">
                        Position <span class="font-bold text-slate-600" id="my-position">#{{ $myPosition }}</span>
                        &nbsp;·&nbsp;
                        Est. <span class="font-bold text-slate-600" id="my-est-time">~{{ round($myPosition * $avgServeTime) }} min</span>
                    </div>
                    @endif
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
    // Avg serve time from server (minutes per student)
    let avgServeMins = {{ $avgServeTime }};
    let queuePaused  = {{ $queuePaused ? 'true' : 'false' }};

    function updateEstWait(waitingCount, avg, paused) {
        const estEl   = document.getElementById('est-wait-time');
        const posEl   = document.getElementById('my-position');
        const myEstEl = document.getElementById('my-est-time');

        if (estEl) {
            if (paused) {
                estEl.innerText = 'On Break';
            } else if (waitingCount > 0) {
                estEl.innerText = '~' + Math.round(waitingCount * avg) + ' min';
            } else {
                estEl.innerText = 'Ready';
            }
        }

        // Update per-ticket estimate if position is known
        if (posEl && myEstEl) {
            const pos = parseInt(posEl.innerText.replace('#', ''), 10);
            if (!isNaN(pos)) {
                myEstEl.innerText = '~' + Math.round(pos * avg) + ' min';
            }
        }
    }

    function updatePauseBanner(paused) {
        const banner = document.getElementById('pause-banner');
        if (banner) banner.classList.toggle('hidden', !paused);

        // "Now Serving" badge — swap LIVE ↔ ON BREAK
        const liveEl  = document.getElementById('live-badge');
        const breakEl = document.getElementById('break-badge');
        const numEl   = document.getElementById('current-number');
        if (liveEl)  liveEl.classList.toggle('hidden', paused);
        if (breakEl) breakEl.classList.toggle('hidden', !paused);
        if (numEl) {
            numEl.classList.toggle('text-primary', !paused);
            numEl.classList.toggle('text-slate-300', paused);
        }
    }

    if (window.Echo) {
        window.Echo.channel('queue').listen('.queue.updated', function(data) {
            const currentEl = document.getElementById('current-number');
            const nextEl    = document.getElementById('next-number');
            const waitEl    = document.getElementById('waiting-count');

            if (currentEl && data.current && currentEl.innerText !== data.current) {
                currentEl.style.opacity = '0.3';
                currentEl.style.transition = 'opacity .2s';
                setTimeout(() => { currentEl.innerText = data.current; currentEl.style.opacity = '1'; }, 200);
            }
            if (nextEl) nextEl.innerText = data.next ?? 'Waiting';
            if (waitEl && data.waiting_count !== undefined) waitEl.innerText = data.waiting_count;

            // Update avg and pause state if provided
            if (data.avg_serve_mins) avgServeMins = data.avg_serve_mins;
            if (data.queue_paused !== undefined) queuePaused = data.queue_paused;

            updateEstWait(data.waiting_count ?? 0, avgServeMins, queuePaused);
            updatePauseBanner(queuePaused);
            const myTicketEl = document.getElementById('my-ticket-number');
            const myStatusEl = document.getElementById('my-ticket-status');
            if (!myTicketEl || !myStatusEl) return;

            const myTicket = myTicketEl.innerText.trim();

            if (data.current === myTicket) {
                myStatusEl.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-4 py-1.5 rounded-full bg-green-100 text-green-700';
                myStatusEl.innerText = '🟢 YOUR TURN NOW!';
                showToast("🔔 It's your turn! Please proceed to the window.", 'success');
            }

            if (data.completed_ticket === myTicket) {
                myStatusEl.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-4 py-1.5 rounded-full bg-blue-100 text-blue-700';
                myStatusEl.innerText = '✅ COMPLETED';
                showToast("✅ Your transaction is done. Thank you for visiting!", 'success');
                setTimeout(() => window.location.reload(), 4000);
            }

            if (data.skipped_ticket === myTicket) {
                myStatusEl.className = 'inline-flex items-center gap-1.5 text-xs font-bold px-4 py-1.5 rounded-full bg-red-100 text-red-700';
                myStatusEl.innerText = '⚠️ SKIPPED';
                showToast("⚠️ You were skipped. Please re-queue at the window.", 'warning');
                setTimeout(() => window.location.reload(), 4000);
            }
        });

        window.Echo.channel('purposes').listen('.purposes.updated', function(data) {
            const purposeSelect = document.querySelector('select[name="purpose_id"]');
            if (!purposeSelect || !data.purposes) return;
            const currentValue = purposeSelect.value;
            purposeSelect.innerHTML = '<option value="">Choose one...</option>';
            data.purposes.forEach(purpose => {
                if (purpose.is_active) {
                    const option = document.createElement('option');
                    option.value = purpose.id;
                    option.textContent = purpose.name;
                    if (purpose.id == currentValue) option.selected = true;
                    purposeSelect.appendChild(option);
                }
            });
            showToast("📋 Purpose options updated!", 'success');
        });

    } else {
        // Fallback polling
        setInterval(() => {
            fetch('{{ route("api.queueStatus") }}').then(r => r.json()).then(data => {
                const c = document.getElementById('current-number');
                const n = document.getElementById('next-number');
                const w = document.getElementById('waiting-count');
                if (c && data.current) c.innerText = data.current;
                if (n && data.next)    n.innerText = data.next;
                if (data.avg_serve_mins) avgServeMins = data.avg_serve_mins;
                if (data.queue_paused !== undefined) queuePaused = data.queue_paused;
                const wCount = w ? parseInt(w.innerText, 10) : 0;
                updateEstWait(wCount, avgServeMins, queuePaused);
                updatePauseBanner(queuePaused);
            });
            fetch('{{ route("api.purposes") }}').then(r => r.json()).then(data => {
                const purposeSelect = document.querySelector('select[name="purpose_id"]');
                if (!purposeSelect || !data.purposes) return;
                const currentValue = purposeSelect.value;
                purposeSelect.innerHTML = '<option value="">Choose one...</option>';
                data.purposes.forEach(purpose => {
                    const option = document.createElement('option');
                    option.value = purpose.id;
                    option.textContent = purpose.name;
                    if (purpose.id == currentValue) option.selected = true;
                    purposeSelect.appendChild(option);
                });
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
