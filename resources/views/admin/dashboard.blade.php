@extends('layouts.app')
@section('page-title', 'Dashboard')

@section('content')
@php
    $queueOperational = (bool) $selectedDepartment?->is_active;
@endphp

<div class="bg-white rounded-2xl border border-slate-200 p-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Department Queue</div>
        <div class="text-lg font-black text-slate-900">{{ $selectedDepartment?->name ?? 'No Department' }}</div>
    </div>
    @if($isAdmin)
        <form action="{{ route('admin.index') }}" method="GET" class="flex items-center gap-2">
            <label for="admin-department" class="sr-only">Department</label>
            <select id="admin-department" name="department_id" onchange="this.form.submit()"
                class="min-w-48 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected($selectedDepartment?->id === $department->id)>
                        {{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}
                    </option>
                @endforeach
            </select>
        </form>
    @else
        <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-blue-50 text-blue-700">Assigned Department</span>
    @endif
</div>

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
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button type="button" id="pause-btn" onclick="sendPauseAction('pause')"
                    class="{{ !$queueOperational || $queuePaused ? 'hidden' : '' }} inline-flex items-center gap-1.5 bg-yellow-100 hover:bg-yellow-200 border border-yellow-300 text-yellow-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors disabled:opacity-40">
                    <i class="bi bi-pause-fill"></i> Pause Queue
                </button>
                <button type="button" id="resume-btn" onclick="sendPauseAction('resume')"
                    class="{{ !$queueOperational || !$queuePaused ? 'hidden' : '' }} inline-flex items-center gap-1.5 bg-green-100 hover:bg-green-200 border border-green-300 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors disabled:opacity-40">
                    <i class="bi bi-play-fill"></i> Resume Queue
                </button>
                <span id="queue-status-badge" class="inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1 rounded-full
                    {{ !$queueOperational ? 'bg-slate-100 text-slate-500' : ($queuePaused ? 'bg-yellow-50 text-yellow-700' : 'badge-live bg-green-50 text-green-700') }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ !$queueOperational ? 'bg-slate-400' : ($queuePaused ? 'bg-yellow-500' : 'bg-green-500') }}"></span>
                    {{ !$queueOperational ? 'INACTIVE' : ($queuePaused ? 'PAUSED' : 'LIVE') }}
                </span>
            </div>
        </div>

        {{-- Paused banner --}}
        <div id="paused-banner" class="{{ $queuePaused || !$queueOperational ? '' : 'hidden' }} flex items-center gap-2.5 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-xl mb-4">
            <i class="bi bi-pause-circle-fill text-yellow-500 text-base shrink-0"></i>
            <div>
                <span class="font-semibold" id="paused-banner-title">{{ $queueOperational ? 'Queue is paused.' : 'Department is inactive.' }}</span>
                <span id="paused-banner-reason"> {{ $queueOperational ? 'Auto-skip is disabled. Students see a break notice.' : 'Queue actions and new tickets are disabled.' }}</span>
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
                    <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                    <button type="submit" data-department-active {{ !$queueOperational ? 'disabled' : '' }} class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="bi bi-skip-forward-fill"></i> Skip / No Show
                    </button>
                </form>
                <form action="{{ route('admin.complete', $currentServing->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                    <button type="submit" data-department-active {{ !$queueOperational ? 'disabled' : '' }} class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="bi bi-check-lg"></i> Complete
                    </button>
                </form>
                <form action="{{ route('admin.callNext') }}" method="POST">
                    @csrf
                    <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                    <button type="submit" data-queue-running data-empty="{{ $waitingCount == 0 ? '1' : '0' }}" {{ $waitingCount == 0 || !$queueOperational || $queuePaused ? 'disabled' : '' }}
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
                    <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                    <button type="submit" data-queue-running data-empty="{{ $waitingCount == 0 ? '1' : '0' }}" {{ $waitingCount == 0 || !$queueOperational || $queuePaused ? 'disabled' : '' }}
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
                 <span class="text-xs text-slate-400" id="pagination-summary">
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
    const SELECTED_DEPARTMENT_ID = {{ $selectedDepartment?->id ?? 'null' }};
    const QUEUE_OPERATIONAL = {{ $queueOperational ? 'true' : 'false' }};
    let   queueIsPaused     = {{ $queuePaused ? 'true' : 'false' }};
    let   lunchBreakStart   = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakStart)->format('g:i A') }}";
    let   lunchBreakEnd     = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd)->format('g:i A') }}";

    async function sendPauseAction(action) {
        if (!SELECTED_DEPARTMENT_ID || !QUEUE_OPERATIONAL) return;

        const pauseBtn = document.getElementById('pause-btn');
        const resumeBtn = document.getElementById('resume-btn');
        if (pauseBtn) pauseBtn.disabled = true;
        if (resumeBtn) resumeBtn.disabled = true;

        try {
            const res = await fetch(TOGGLE_PAUSE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept':       'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action, department_id: SELECTED_DEPARTMENT_ID }),
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Unable to update the queue.');
            }

            applyPauseState(data.queue_paused, 'manual');
            showToast(data.message, data.queue_paused ? 'warning' : 'success');
        } catch (e) {
            showToast(e.message || 'Failed to update the queue. Please try again.', 'warning');
        } finally {
            if (pauseBtn) pauseBtn.disabled = false;
            if (resumeBtn) resumeBtn.disabled = false;
        }
    }

    function applyPauseState(isPaused, source = 'auto') {
        queueIsPaused = Boolean(isPaused);
        const pauseBtn     = document.getElementById('pause-btn');
        const resumeBtn    = document.getElementById('resume-btn');
        const pausedBanner = document.getElementById('paused-banner');
        const bannerTitle  = document.getElementById('paused-banner-title');
        const bannerReason = document.getElementById('paused-banner-reason');
        const statusBadge  = document.getElementById('queue-status-badge');

        if (QUEUE_OPERATIONAL) {
            pauseBtn?.classList.toggle('hidden', queueIsPaused);
            resumeBtn?.classList.toggle('hidden', !queueIsPaused);
        } else {
            pauseBtn?.classList.add('hidden');
            resumeBtn?.classList.add('hidden');
        }

        if (bannerTitle) {
            bannerTitle.innerText = QUEUE_OPERATIONAL ? 'Queue is paused.' : 'Department is inactive.';
        }
        if (bannerReason && QUEUE_OPERATIONAL) {
            if (queueIsPaused && source === 'auto') {
                bannerReason.innerText = ' Lunch break in progress. Auto-skip is disabled. Students see a break notice.';
            } else if (queueIsPaused) {
                bannerReason.innerText = ' Manually paused. Auto-skip is disabled. Students see a break notice.';
            }
        }

        if (pausedBanner) pausedBanner.classList.toggle('hidden', QUEUE_OPERATIONAL && !queueIsPaused);

        if (statusBadge) {
            const label = !QUEUE_OPERATIONAL ? 'INACTIVE' : (queueIsPaused ? 'PAUSED' : 'LIVE');
            const colors = !QUEUE_OPERATIONAL
                ? ['bg-slate-100', 'text-slate-500', 'bg-slate-400']
                : (queueIsPaused
                    ? ['bg-yellow-50', 'text-yellow-700', 'bg-yellow-500']
                    : ['bg-green-50', 'text-green-700', 'bg-green-500']);
            statusBadge.className = `inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1 rounded-full ${colors[0]} ${colors[1]}`;
            statusBadge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${colors[2]}"></span>${label}`;
        }

        document.querySelectorAll('[data-department-active]').forEach(button => {
            button.disabled = !QUEUE_OPERATIONAL;
        });
        document.querySelectorAll('[data-queue-running]').forEach(button => {
            button.disabled = !QUEUE_OPERATIONAL || queueIsPaused || button.dataset.empty === '1';
        });
    }

    function updatePauseResumeUI(isPaused, lbStart, lbEnd, pauseSource = 'manual') {
        const formatTime = value => {
            if (!value || !value.includes(':')) return value;
            const [hour, minute] = value.split(':').map(Number);
            const suffix = hour >= 12 ? 'PM' : 'AM';
            return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix}`;
        };

        if (lbStart) lunchBreakStart = formatTime(lbStart);
        if (lbEnd) lunchBreakEnd = formatTime(lbEnd);

        const schedule = document.getElementById('lunch-break-schedule');
        const endDisplay = document.getElementById('lunch-break-end-display');
        if (schedule) schedule.innerText = `${lunchBreakStart} - ${lunchBreakEnd}`;
        if (endDisplay) endDisplay.innerText = lunchBreakEnd;

        applyPauseState(isPaused, pauseSource === 'lunch' ? 'auto' : 'manual');
    }

    // ── Auto-skip countdown timer ─────────────────────────────────────────────
    const AUTO_SKIP_SECONDS = 3 * 60;

    function updateAutoSkipTimer() {
        const timerEl = document.getElementById('auto-skip-timer');
        const labelEl = document.getElementById('auto-skip-label');
        if (!timerEl || !labelEl) return;

        if (queueIsPaused || !QUEUE_OPERATIONAL) {
            timerEl.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-yellow-100 text-yellow-700';
            labelEl.innerText = 'Auto-skip paused';
            return;
        }

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

    applyPauseState(queueIsPaused);
    updateAutoSkipTimer();
    setInterval(updateAutoSkipTimer, 1000);
</script>
@endsection
