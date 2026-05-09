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
                {{-- Ticket Mode dropdown + Pause/Resume buttons --}}
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <form action="{{ route('admin.togglePause') }}" method="POST" id="ticket-mode-form">
                        @csrf
                        <div class="flex items-center gap-1.5">
                            <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Ticket Mode:</label>
                            <select id="ticket-mode-select"
                                onchange="handleTicketModeChange(this)"
                                data-paused="{{ $queuePaused ? '1' : '0' }}"
                                class="text-[11px] font-bold px-3 py-1 rounded-full border transition-colors cursor-pointer focus:outline-none
                                       {{ $queuePaused
                                           ? 'bg-yellow-100 border-yellow-300 text-yellow-700 hover:bg-yellow-200'
                                           : 'bg-green-100 border-green-300 text-green-700 hover:bg-green-200' }}"
                                id="ticket-mode-select">
                                <option value="automatic" {{ !$queuePaused ? 'selected' : '' }}>⚡ Automatic</option>
                                <option value="manual"    {{ $queuePaused  ? 'selected' : '' }}>⏸ Manual</option>
                            </select>
                        </div>
                    </form>

                    {{-- Pause / Resume buttons — only visible in Manual mode --}}
                    <div id="pause-resume-btns" class="{{ $queuePaused ? 'flex' : 'hidden' }} items-center gap-1.5">
                        {{-- Pause button — shown when queue is running (not paused) --}}
                        <form action="{{ route('admin.togglePause') }}" method="POST" id="pause-btn-form"
                              class="{{ $queuePaused ? 'hidden' : '' }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 bg-yellow-100 hover:bg-yellow-200 border border-yellow-300 text-yellow-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors">
                                <i class="bi bi-pause-fill"></i> Pause
                            </button>
                        </form>
                        {{-- Resume button — shown when queue is paused --}}
                        <form action="{{ route('admin.togglePause') }}" method="POST" id="resume-btn-form"
                              class="{{ $queuePaused ? '' : 'hidden' }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 bg-green-100 hover:bg-green-200 border border-green-300 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors">
                                <i class="bi bi-play-fill"></i> Resume
                            </button>
                        </form>
                    </div>
                </div>
                <span class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
                </span>
            </div>
        </div>

        {{-- Paused banner --}}
        <div id="paused-banner" class="{{ $queuePaused ? '' : 'hidden' }} flex items-center gap-2.5 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-xl mb-4">
            <i class="bi bi-pause-circle-fill text-yellow-500 text-base"></i>
            <span class="font-semibold">Queue is in Manual mode (paused).</span> Auto-skip is disabled. Students see a break notice.
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
    // ── Ticket Mode dropdown handler ───────────────────────────────────────────
    function handleTicketModeChange(select) {
        const btnsWrap   = document.getElementById('pause-resume-btns');
        const pauseForm  = document.getElementById('pause-btn-form');
        const resumeForm = document.getElementById('resume-btn-form');
        const modeSelect = document.getElementById('ticket-mode-select');

        if (select.value === 'manual') {
            // Show pause/resume area
            btnsWrap.classList.remove('hidden');
            btnsWrap.classList.add('flex');

            // Determine which button to show based on current paused state
            const isPaused = modeSelect.dataset.paused === '1';
            if (isPaused) {
                pauseForm.classList.add('hidden');
                resumeForm.classList.remove('hidden');
            } else {
                pauseForm.classList.remove('hidden');
                resumeForm.classList.add('hidden');
                // Auto-submit to pause immediately when switching to Manual
                document.getElementById('ticket-mode-form').submit();
            }
        } else {
            // Automatic selected — hide buttons and submit to resume
            btnsWrap.classList.add('hidden');
            btnsWrap.classList.remove('flex');
            document.getElementById('ticket-mode-form').submit();
        }
    }

    // ── Sync pause/resume buttons from real-time data ──────────────────────────
    function updatePauseResumeUI(isPaused) {
        const modeSelect   = document.getElementById('ticket-mode-select');
        const btnsWrap     = document.getElementById('pause-resume-btns');
        const pauseForm    = document.getElementById('pause-btn-form');
        const resumeForm   = document.getElementById('resume-btn-form');
        const pausedBanner = document.getElementById('paused-banner');

        if (!modeSelect) return;

        // Store current paused state on the element
        modeSelect.dataset.paused = isPaused ? '1' : '0';

        const isManualMode = modeSelect.value === 'manual';

        // Only update dropdown color — do NOT change the selected value.
        // The staff chose Manual mode; resuming doesn't switch them back to Automatic.
        if (isPaused) {
            modeSelect.className = modeSelect.className
                .replace(/bg-green-\S+|border-green-\S+|text-green-\S+|hover:bg-green-\S+/g, '')
                .trim() + ' bg-yellow-100 border-yellow-300 text-yellow-700 hover:bg-yellow-200';
        } else {
            modeSelect.className = modeSelect.className
                .replace(/bg-yellow-\S+|border-yellow-\S+|text-yellow-\S+|hover:bg-yellow-\S+/g, '')
                .trim() + ' bg-green-100 border-green-300 text-green-700 hover:bg-green-200';
        }

        // If in Manual mode, always keep the buttons visible —
        // just swap which button (Pause vs Resume) is shown.
        if (isManualMode) {
            btnsWrap.classList.remove('hidden');
            btnsWrap.classList.add('flex');
            if (isPaused) {
                pauseForm.classList.add('hidden');
                resumeForm.classList.remove('hidden');
            } else {
                pauseForm.classList.remove('hidden');
                resumeForm.classList.add('hidden');
            }
        } else {
            // Automatic mode — hide buttons entirely
            btnsWrap.classList.add('hidden');
            btnsWrap.classList.remove('flex');
        }

        // Update paused banner
        if (pausedBanner) pausedBanner.classList.toggle('hidden', !isPaused);
    }

    // ── Auto-skip countdown timer ──────────────────────────────────────────────
    const AUTO_SKIP_SECONDS = 3 * 60; // must match AutoSkipQueue command (3 minutes)

    function updateAutoSkipTimer() {
        const timerEl = document.getElementById('auto-skip-timer');
        const labelEl = document.getElementById('auto-skip-label');
        if (!timerEl || !labelEl) return;

        const servedAt  = parseInt(timerEl.dataset.servedAt, 10);
        const now       = Math.floor(Date.now() / 1000);
        const elapsed   = now - servedAt;
        const remaining = AUTO_SKIP_SECONDS - elapsed;

        if (remaining <= 0) {
            // Already past the threshold — auto-skip will fire on next scheduler run
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700';
            labelEl.innerText = 'Auto-skipping...';
            return;
        }

        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        const display = mins + ':' + String(secs).padStart(2, '0');

        if (remaining <= 30) {
            // Last 30 seconds — red urgent
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700 animate-pulse';
        } else if (remaining <= 60) {
            // Last 1 minute — orange warning
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-orange-100 text-orange-700';
        } else {
            // Normal — gray
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-500';
        }

        labelEl.innerText = 'Auto-skip in ' + display;
    }

    // Run immediately then every second
    updateAutoSkipTimer();
    setInterval(updateAutoSkipTimer, 1000);
</script>
@endsection
