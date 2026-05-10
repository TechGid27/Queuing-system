@extends('layouts.app')
@section('page-title', 'Dashboard')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-yellow-50 flex items-center justify-center text-yellow-600 shrink-0">
            <i class="bi bi-hourglass-split text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Waiting</div>
            <div class="text-2xl font-black text-yellow-600 leading-tight" id="stat-waiting">{{ $waitingCount }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
            <i class="bi bi-person-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Serving</div>
            <div class="text-2xl font-black text-blue-600 leading-tight" id="stat-serving">{{ $currentServing ? 1 : 0 }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-600 shrink-0">
            <i class="bi bi-check-circle-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Completed</div>
            <div class="text-2xl font-black text-green-600 leading-tight" id="stat-completed">{{ $completedCount }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-600 shrink-0">
            <i class="bi bi-x-circle-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">No Response</div>
            <div class="text-2xl font-black text-red-600 leading-tight" id="stat-skipped">{{ $skippedCount }}</div>
        </div>
    </div>
</div>

{{-- Main panels --}}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Now Serving --}}
    <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 p-5 lg:p-7">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Now Serving</h2>
            <div class="flex items-center gap-2">
                {{-- Ticket Mode dropdown + Pause/Resume buttons (all AJAX — no page reload) --}}
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <div class="flex items-center gap-1.5">
                        <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Ticket Mode:</label>
                        <select id="ticket-mode-select"
                            onchange="handleTicketModeChange(this)"
                            data-paused="{{ $queuePaused ? '1' : '0' }}"
                            class="text-[11px] font-bold px-3 py-1 rounded-full border transition-colors cursor-pointer focus:outline-none
                                   {{ $queuePaused
                                       ? 'bg-yellow-100 border-yellow-300 text-yellow-700 hover:bg-yellow-200'
                                       : 'bg-green-100 border-green-300 text-green-700 hover:bg-green-200' }}">
                            <option value="automatic" {{ !$queuePaused ? 'selected' : '' }}>⚡ Automatic</option>
                            <option value="manual"    {{ $queuePaused  ? 'selected' : '' }}>⏸ Manual</option>
                        </select>
                    </div>

                    {{-- Pause / Resume buttons — only visible in Manual mode --}}
                    <div id="pause-resume-btns" class="{{ $queuePaused ? 'flex' : 'hidden' }} items-center gap-1.5">
                        {{-- Pause button — shown when queue is running (not paused) --}}
                        <button id="pause-btn" onclick="sendPauseAction('pause')"
                            class="{{ $queuePaused ? 'hidden' : '' }} inline-flex items-center gap-1.5 bg-yellow-100 hover:bg-yellow-200 border border-yellow-300 text-yellow-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors">
                            <i class="bi bi-pause-fill"></i> Pause
                        </button>
                        {{-- Resume button — shown when queue is paused --}}
                        <button id="resume-btn" onclick="sendPauseAction('resume')"
                            class="{{ $queuePaused ? '' : 'hidden' }} inline-flex items-center gap-1.5 bg-green-100 hover:bg-green-200 border border-green-300 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors">
                            <i class="bi bi-play-fill"></i> Resume
                        </button>
                    </div>
                </div>
                <span class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                </span>
            </div>
        </div>

        {{-- Paused banner --}}
        <div id="paused-banner" class="{{ $queuePaused ? '' : 'hidden' }} flex items-center gap-2.5 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-xl mb-4">
            <i class="bi bi-pause-circle-fill text-yellow-500 text-base shrink-0"></i>
            <div>
                <span class="font-semibold">Queue is paused.</span>
                <span id="paused-banner-reason"> Auto-skip is disabled. Students see a break notice.</span>
                <div class="text-xs text-yellow-600 mt-0.5">
                    Lunch break: <span id="lunch-break-schedule">{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakStart)->format('g:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd)->format('g:i A') }}</span>
                    &nbsp;·&nbsp; Auto-resumes at <strong id="lunch-break-end-display">{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd)->format('g:i A') }}</strong>
                </div>
            </div>
        </div>

        <div id="now-serving-panel">
        @if($currentServing)
            <div class="text-center py-4">
                <div class="ticket-xl text-red-600 mb-3">{{ $currentServing->ticket_number }}</div>
                <div class="text-lg font-bold text-slate-800">{{ $currentServing->name }}</div>
                <div class="text-sm text-slate-400 mt-1">{{ $currentServing->purpose }}</div>
                <div class="mt-2 flex items-center justify-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                        <i class="bi bi-phone"></i> {{ $currentServing->phone_number }}
                    </span>
                    <span id="auto-skip-timer"
                        class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full"
                        data-served-at="{{ ($currentServing->served_at ?? $currentServing->updated_at)->timestamp }}">
                        <i class="bi bi-clock"></i> <span id="auto-skip-label">--:--</span>
                    </span>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-5 mt-2 flex flex-wrap gap-2 justify-center">
                <form action="{{ route('admin.reject', $currentServing->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                        <i class="bi bi-skip-forward-fill"></i> Skip / No Show
                    </button>
                </form>
                <form action="{{ route('admin.complete', $currentServing->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                        <i class="bi bi-check-lg"></i> Complete
                    </button>
                </form>
                <form action="{{ route('admin.callNext') }}" method="POST">
                    @csrf
                    <button type="submit" {{ $waitingCount == 0 ? 'disabled' : '' }}
                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="bi bi-arrow-right-circle-fill"></i> Call Next
                    </button>
                </form>
            </div>
        @else
            <div class="text-center py-10">
                <div class="text-5xl mb-3">📭</div>
                <div class="text-base font-semibold text-slate-400">No student is currently being served</div>
                <form action="{{ route('admin.callNext') }}" method="POST" class="mt-6 inline-block">
                    @csrf
                    <button type="submit" {{ $waitingCount == 0 ? 'disabled' : '' }}
                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="bi bi-play-circle-fill"></i> Call First Student
                    </button>
                </form>
            </div>
        @endif
        </div>{{-- #now-serving-panel --}}
    </div>

    {{-- Waiting List --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">Waiting List</h2>
            <span id="waiting-badge" class="bg-yellow-50 text-yellow-700 text-xs font-bold px-2.5 py-1 rounded-full">{{ $waitingCount }} in queue</span>
        </div>
        <div class="overflow-y-auto flex-1" id="waiting-list-body">
            @forelse($waitingStudents as $i => $student)
                @php $position = ($waitingStudents->currentPage() - 1) * $waitingStudents->perPage() + $i + 1; @endphp
                <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0
                        {{ $position === 1 ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500' }}">
                        {{ $position }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-slate-800 truncate">{{ $student->ticket_number }}</div>
                        <div class="text-xs text-slate-400 truncate">{{ $student->name }}</div>
                    </div>
                    <span class="bg-slate-100 text-slate-500 text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 max-w-[80px] truncate">
                        {{ $student->purpose }}
                    </span>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <i class="bi bi-inbox text-3xl mb-2"></i>
                    <span class="text-sm">Queue is empty</span>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($waitingStudents->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <span class="text-xs text-slate-400">
                    Page {{ $waitingStudents->currentPage() }} of {{ $waitingStudents->lastPage() }}
                </span>
                <div class="flex items-center gap-1">
                    @if($waitingStudents->onFirstPage())
                        <span class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            <i class="bi bi-chevron-left"></i>
                        </span>
                    @else
                        <a href="{{ $waitingStudents->previousPageUrl() }}"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    @endif

                    @foreach($waitingStudents->getUrlRange(1, $waitingStudents->lastPage()) as $page => $url)
                        <a href="{{ $url }}"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors
                            {{ $page == $waitingStudents->currentPage()
                                ? 'bg-primary text-white'
                                : 'text-slate-600 bg-slate-100 hover:bg-slate-200' }}">
                            {{ $page }}
                        </a>
                    @endforeach

                    @if($waitingStudents->hasMorePages())
                        <a href="{{ $waitingStudents->nextPageUrl() }}"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    @else
                        <span class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            <i class="bi bi-chevron-right"></i>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

</div>
@endsection

@include('admin.dashboard_realtime')

@section('scripts')
<script>
    const TOGGLE_PAUSE_URL  = "{{ route('admin.togglePause') }}";
    const CSRF_TOKEN        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let   lunchBreakStart   = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakStart)->format('g:i A') }}";
    let   lunchBreakEnd     = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd)->format('g:i A') }}";

    // ── Core AJAX helper ──────────────────────────────────────────────────────
    async function sendPauseAction(action) {
        try {
            const res = await fetch(TOGGLE_PAUSE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept':       'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action }),
            });

            const data = await res.json();
            if (data.success) {
                applyPauseState(data.queue_paused, 'manual');
                showToast(data.message, data.queue_paused ? 'warning' : 'success');
            }
        } catch (e) {
            showToast('Failed to update queue mode. Please try again.', 'warning');
        }
    }

    // ── Ticket Mode dropdown handler ──────────────────────────────────────────
    function handleTicketModeChange(select) {
        if (select.value === 'manual') {
            sendPauseAction('pause');
        } else {
            sendPauseAction('resume');
        }
    }

    // ── Apply pause state to all UI elements (no reload) ─────────────────────
    // source: 'manual' = staff clicked, 'auto' = lunch break scheduler
    function applyPauseState(isPaused, source = 'auto') {
        const modeSelect   = document.getElementById('ticket-mode-select');
        const btnsWrap     = document.getElementById('pause-resume-btns');
        const pauseBtn     = document.getElementById('pause-btn');
        const resumeBtn    = document.getElementById('resume-btn');
        const pausedBanner = document.getElementById('paused-banner');
        const bannerReason = document.getElementById('paused-banner-reason');

        if (!modeSelect) return;

        modeSelect.dataset.paused = isPaused ? '1' : '0';
        modeSelect.value          = isPaused ? 'manual' : 'automatic';

        // Dropdown color
        if (isPaused) {
            modeSelect.className = modeSelect.className
                .replace(/bg-green-\S+|border-green-\S+|text-green-\S+|hover:bg-green-\S+/g, '')
                .trim() + ' bg-yellow-100 border-yellow-300 text-yellow-700 hover:bg-yellow-200';
        } else {
            modeSelect.className = modeSelect.className
                .replace(/bg-yellow-\S+|border-yellow-\S+|text-yellow-\S+|hover:bg-yellow-\S+/g, '')
                .trim() + ' bg-green-100 border-green-300 text-green-700 hover:bg-green-200';
        }

        // Pause/Resume buttons
        const isManual = modeSelect.value === 'manual';
        if (isManual) {
            btnsWrap.classList.remove('hidden');
            btnsWrap.classList.add('flex');
            if (isPaused) {
                pauseBtn?.classList.add('hidden');
                resumeBtn?.classList.remove('hidden');
            } else {
                pauseBtn?.classList.remove('hidden');
                resumeBtn?.classList.add('hidden');
            }
        } else {
            btnsWrap.classList.add('hidden');
            btnsWrap.classList.remove('flex');
        }

        // Banner text — distinguish auto lunch break vs manual pause
        if (bannerReason) {
            if (isPaused && source === 'auto') {
                bannerReason.innerText = ' Lunch break in progress. Auto-skip is disabled. Students see a break notice.';
            } else if (isPaused) {
                bannerReason.innerText = ' Manually paused. Auto-skip is disabled. Students see a break notice.';
            }
        }

        if (pausedBanner) pausedBanner.classList.toggle('hidden', !isPaused);
    }

    // ── Called from dashboard_realtime when broadcast arrives ─────────────────
    function updatePauseResumeUI(isPaused, lbStart, lbEnd) {
        // Update lunch break times if provided by the API
        if (lbStart) lunchBreakStart = lbStart;
        if (lbEnd)   lunchBreakEnd   = lbEnd;

        // Determine if this is an auto lunch-break pause by checking current time
        const now  = new Date();
        const hhmm = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        const lbStartRaw = "{{ $lunchBreakStart }}"; // e.g. "12:00"
        const lbEndRaw   = "{{ $lunchBreakEnd }}";   // e.g. "13:30"
        const isLunchTime = hhmm >= lbStartRaw && hhmm < lbEndRaw;

        applyPauseState(isPaused, isPaused && isLunchTime ? 'auto' : 'manual');
    }

    // ── Auto-skip countdown timer ─────────────────────────────────────────────
    const AUTO_SKIP_SECONDS = 3 * 60;

    function updateAutoSkipTimer() {
        const timerEl = document.getElementById('auto-skip-timer');
        const labelEl = document.getElementById('auto-skip-label');
        if (!timerEl || !labelEl) return;

        const servedAt  = parseInt(timerEl.dataset.servedAt, 10);
        const now       = Math.floor(Date.now() / 1000);
        const elapsed   = now - servedAt;
        const remaining = AUTO_SKIP_SECONDS - elapsed;

        if (remaining <= 0) {
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700';
            labelEl.innerText = 'Auto-skipping...';
            return;
        }

        const mins    = Math.floor(remaining / 60);
        const secs    = remaining % 60;
        const display = mins + ':' + String(secs).padStart(2, '0');

        if (remaining <= 30) {
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700 animate-pulse';
        } else if (remaining <= 60) {
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-orange-100 text-orange-700';
        } else {
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-500';
        }

        labelEl.innerText = 'Auto-skip in ' + display;
    }

    updateAutoSkipTimer();
    setInterval(updateAutoSkipTimer, 1000);
</script>
@endsection
