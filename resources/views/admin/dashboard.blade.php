@extends('layouts.app')
@section('page-title', 'Queue Console')

@section('content')
@php
    $queueOperational = (bool) $selectedDepartment?->is_active;
@endphp

<div class="focus-hide bg-white rounded-2xl border border-slate-200 p-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
<div class="focus-hide grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-5">
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
            <div class="text-2xl font-black text-blue-600 leading-tight" id="stat-serving">{{ $servingCount ?? ($currentServing ? 1 : 0) }}</div>
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
                @if(auth()->user()->role !== 'admin')
                <div class="inline-flex items-center bg-slate-100 rounded-full p-0.5" role="group" aria-label="Pause mode">
                    <button type="button" id="mode-auto-btn" onclick="sendPauseMode('auto')"
                        class="text-[11px] font-bold px-3 py-1 rounded-full transition-colors {{ ($autoPauseEnabled ?? true) ? 'bg-white shadow text-slate-800' : 'text-slate-400' }}">
                        Auto
                    </button>
                    <button type="button" id="mode-manual-btn" onclick="sendPauseMode('manual')"
                        class="text-[11px] font-bold px-3 py-1 rounded-full transition-colors {{ ($autoPauseEnabled ?? true) ? 'text-slate-400' : 'bg-white shadow text-slate-800' }}">
                        Manual
                    </button>
                </div>
                <button type="button" id="pause-btn" onclick="sendPauseAction('pause')"
                    class="{{ !$queueOperational || $queuePaused || ($autoPauseEnabled ?? true) ? 'hidden' : '' }} inline-flex items-center gap-1.5 bg-yellow-100 hover:bg-yellow-200 border border-yellow-300 text-yellow-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors disabled:opacity-40">
                    <i class="bi bi-pause-fill"></i> Pause Queue
                </button>
                <button type="button" id="resume-btn" onclick="sendPauseAction('resume')"
                    class="{{ !$queueOperational || !$queuePaused || ($autoPauseEnabled ?? true) ? 'hidden' : '' }} inline-flex items-center gap-1.5 bg-green-100 hover:bg-green-200 border border-green-300 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full transition-colors disabled:opacity-40">
                    <i class="bi bi-play-fill"></i> Resume Queue
                </button>
                @else
                <span id="pause-mode-label" class="text-[11px] font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-500">
                    {{ ($autoPauseEnabled ?? true) ? 'AUTO' : 'MANUAL' }}
                </span>
                @endif
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

        @if(auth()->user()->role !== 'admin' && ($counters ?? collect())->where('is_active', true)->isNotEmpty())
        <div id="window-focus-bar" class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <label for="window-select" class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">My window</label>
                <select id="window-select"
                    class="min-w-40 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">Select window</option>
                    @foreach(($counters ?? collect())->where('is_active', true) as $counter)
                        <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 sm:ml-auto">
                <button type="button" id="focus-mode-btn" disabled
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="bi bi-arrows-fullscreen"></i> Focus window
                </button>
                <button type="button" id="exit-focus-btn"
                    class="focus-only items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors">
                    <i class="bi bi-fullscreen-exit"></i> Exit focus
                </button>
            </div>
        </div>
        @endif

        <div id="now-serving-panel" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @forelse(($counters ?? collect())->where('is_active', true) as $counter)
            @php
                $serving = ($currentServings ?? collect())->firstWhere('counter_id', $counter->id);
                $isBusy = (bool) $serving;
                $isMine = $serving && auth()->user() && $serving->served_by === auth()->user()->id;
            @endphp
            <div class="border border-slate-200 rounded-2xl p-4 {{ $isBusy ? 'bg-white' : 'bg-slate-50' }}" data-counter-card="{{ $counter->id }}">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">{{ $counter->name }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $counter->queue_paused ? 'bg-orange-100 text-orange-600' : ($isBusy ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-400') }}" data-counter-status="{{ $counter->id }}">
                        {{ $counter->queue_paused ? 'PAUSED' : ($isBusy ? 'SERVING' : 'IDLE') }}
                    </span>
                </div>
                <div data-counter-body="{{ $counter->id }}">
                @if($serving)
                    <div class="text-center py-2">
                        <div class="ticket-lg text-red-600 mb-2">{{ $serving->ticket_number }}</div>
                        <div class="text-sm font-bold text-slate-800">{{ $serving->name }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">{{ $serving->purpose }}</div>
                        <div class="mt-2 flex items-center justify-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-phone"></i> {{ $serving->phone_number }}
                            </span>
                            @if($serving->servedBy)
                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-person"></i> {{ $serving->servedBy->name }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-3 mt-2 flex flex-wrap gap-2 justify-center">
                        <form action="{{ route('admin.reject', $serving->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                            <button type="submit" data-department-active {{ !$queueOperational ? 'disabled' : '' }} class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3 py-2 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="bi bi-skip-forward-fill"></i> Skip
                            </button>
                        </form>
                        <form action="{{ route('admin.complete', $serving->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                            <button type="submit" data-department-active {{ !$queueOperational ? 'disabled' : '' }} class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-2 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="bi bi-check-lg"></i> Complete
                            </button>
                        </form>
                    </div>
                @else
                    <div class="text-center py-6 text-slate-400">
                        <div class="text-3xl mb-2">📭</div>
                        <div class="text-xs font-semibold">No student at {{ $counter->name }}</div>
                        @if(auth()->user()->role !== 'admin')
                        <form action="{{ route('admin.callNext') }}" method="POST" class="mt-3">
                            @csrf
                            <input type="hidden" name="department_id" value="{{ $selectedDepartment?->id }}">
                            <input type="hidden" name="counter_id" value="{{ $counter->id }}">
                            <button type="submit" data-queue-running data-empty="{{ $waitingCount == 0 ? '1' : '0' }}" data-current="0" data-counter-id="{{ $counter->id }}" {{ $waitingCount == 0 || !$queueOperational || $queuePaused || $counter->queue_paused ? 'disabled' : '' }}
                                class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="bi bi-play-circle-fill"></i> Call Next here
                            </button>
                        </form>
                        @endif
                    </div>
                @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-slate-400 md:col-span-2">
                <div class="text-5xl mb-3">📭</div>
                <div class="text-base font-semibold">No active counters. Ask an admin to add counters.</div>
            </div>
        @endforelse
        {{-- Legacy single-lane fallback (departments without counters seeded yet) --}}
        @if(($counters ?? collect())->where('is_active', true)->isEmpty() && ($currentServing ?? null))
            <div class="text-center py-4 md:col-span-2">
                <div class="ticket-xl text-red-600 mb-3">{{ $currentServing->ticket_number }}</div>
                <div class="text-lg font-bold text-slate-800">{{ $currentServing->name }}</div>
                <div class="text-sm text-slate-400 mt-1">{{ $currentServing->purpose }}</div>
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
<style>
    [data-counter-card].focused-window {
        border-color: #bfdbfe;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .focus-only { display: none !important; }
    body.focus-mode .focus-hide { display: none !important; }
    body.focus-mode .focus-only { display: inline-flex !important; }
    body.focus-mode #now-serving-panel { grid-template-columns: 1fr !important; }
    body.focus-mode #now-serving-panel [data-counter-card] { display: none; }
    body.focus-mode #now-serving-panel [data-counter-card].focused-window { display: block; }
    body.focus-mode .ticket-lg { font-size: 4rem; line-height: 1; }
    body.focus-mode #waiting-list-body { max-height: 65vh; }
</style>
<script>
    const TOGGLE_PAUSE_URL  = "{{ route('admin.togglePause') }}";
    const PAUSE_MODE_URL = "{{ route('admin.pauseMode') }}";
    const CSRF_TOKEN        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const SELECTED_DEPARTMENT_ID = {{ $selectedDepartment?->id ?? 'null' }};
    const QUEUE_OPERATIONAL = {{ $queueOperational ? 'true' : 'false' }};
    const IS_ADMIN_VIEWER = {{ auth()->user()->role === 'admin' ? 'true' : 'false' }};
    let   queueIsPaused     = {{ $queuePaused ? 'true' : 'false' }};
    let   pauseMode = "{{ ($autoPauseEnabled ?? true) ? 'auto' : 'manual' }}";
    let   departmentIsPaused = {{ $queuePaused ? 'true' : 'false' }};
    let   departmentPauseMode = "{{ ($autoPauseEnabled ?? true) ? 'auto' : 'manual' }}";
    let   departmentPauseSource = @json($selectedDepartment?->lunch_break_paused ? 'lunch' : 'manual');
    let   pauseScope = 'department';
    let   counterState = {!! json_encode((object) collect($counters ?? [])->mapWithKeys(fn ($c) => [(string) $c->id => [
        'queue_paused' => (bool) $c->queue_paused,
        'auto_pause_enabled' => (bool) $c->auto_pause_enabled,
        'lunch_break_paused' => (bool) $c->lunch_break_paused,
    ]])->all()) !!};
    let   lunchBreakStart   = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakStart)->format('g:i A') }}";
    let   lunchBreakEnd     = "{{ \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd)->format('g:i A') }}";

    async function sendPauseAction(action) {
        if (IS_ADMIN_VIEWER) return;
        if (pauseMode !== 'manual') {
            showToast('Switch to Manual mode to use Pause / Resume.', 'warning');
            return;
        }
        if (!SELECTED_DEPARTMENT_ID || !QUEUE_OPERATIONAL) return;

        const pauseBtn = document.getElementById('pause-btn');
        const resumeBtn = document.getElementById('resume-btn');
        if (pauseBtn) pauseBtn.disabled = true;
        if (resumeBtn) resumeBtn.disabled = true;

        try {
            const cid = currentCounterId();
            // Pause/resume only the selected window while the department is
            // running. If the whole department is paused, Resume clears that
            // first so every window comes back.
            const targetCounterId = cid && !departmentIsPaused ? Number(cid) : null;

            const res = await fetch(TOGGLE_PAUSE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept':       'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(Object.assign(
                    { action, department_id: SELECTED_DEPARTMENT_ID },
                    targetCounterId ? { counter_id: targetCounterId } : {}
                )),
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Unable to update the queue.');
            }

            applyPauseResponse(data);
            showToast(data.message, data.queue_paused ? 'warning' : 'success');
            if (typeof refreshDashboard === 'function') refreshDashboard();
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

        updatePauseModeUI(pauseMode);

        if (IS_ADMIN_VIEWER) {
            pauseBtn?.classList.add('hidden');
            resumeBtn?.classList.add('hidden');
        } else if (QUEUE_OPERATIONAL && pauseMode === 'manual') {
            const windowName = currentCounterName();
            const pauseNoun = windowName || 'Queue';
            const resumeNoun = pauseScope === 'window' && windowName ? windowName : 'Queue';
            if (pauseBtn) pauseBtn.innerHTML = `<i class="bi bi-pause-fill"></i> Pause ${pauseNoun}`;
            if (resumeBtn) resumeBtn.innerHTML = `<i class="bi bi-play-fill"></i> Resume ${resumeNoun}`;
            pauseBtn?.classList.toggle('hidden', queueIsPaused);
            resumeBtn?.classList.toggle('hidden', !queueIsPaused);
        } else {
            pauseBtn?.classList.add('hidden');
            resumeBtn?.classList.add('hidden');
        }

        if (bannerTitle) {
            const windowName = currentCounterId() ? currentCounterName() : '';
            bannerTitle.innerText = !QUEUE_OPERATIONAL
                ? 'Department is inactive.'
                : (pauseScope === 'window' && windowName ? `${windowName} is paused.` : 'Queue is paused.');
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
            const windowPaused = pauseScope === 'window' && queueIsPaused;
            const label = !QUEUE_OPERATIONAL
                ? 'INACTIVE'
                : (windowPaused ? 'WINDOW PAUSED' : (queueIsPaused ? 'PAUSED' : 'LIVE'));
            const colors = !QUEUE_OPERATIONAL
                ? ['bg-slate-100', 'text-slate-500', 'bg-slate-400']
                : (queueIsPaused
                    ? (windowPaused
                        ? ['bg-orange-50', 'text-orange-700', 'bg-orange-500']
                        : ['bg-yellow-50', 'text-yellow-700', 'bg-yellow-500'])
                    : ['bg-green-50', 'text-green-700', 'bg-green-500']);
            statusBadge.className = `inline-flex items-center gap-1.5 text-[11px] font-bold px-3 py-1 rounded-full ${colors[0]} ${colors[1]}`;
            statusBadge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${colors[2]}"></span>${label}`;
        }

        document.querySelectorAll('[data-department-active]').forEach(button => {
            button.disabled = !QUEUE_OPERATIONAL;
        });
        document.querySelectorAll('[data-queue-running]').forEach(button => {
            const cid = button.dataset.counterId ? String(button.dataset.counterId) : '';
            const counterPaused = cid && counterState[cid] ? !!counterState[cid].queue_paused : false;
            button.disabled = !QUEUE_OPERATIONAL || departmentIsPaused || counterPaused || button.dataset.empty === '1' || button.dataset.current === '1';
        });
    }

    function updatePauseResumeUI(isPaused, lbStart, lbEnd, pauseSource = 'manual', mode = null) {
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

        if (mode === 'auto' || mode === 'manual') {
            pauseMode = mode;
        }

        applyPauseState(isPaused, pauseSource === 'lunch' ? 'auto' : 'manual');
    }

    function updatePauseModeUI(mode) {
        const autoBtn = document.getElementById('mode-auto-btn');
        const manualBtn = document.getElementById('mode-manual-btn');
        const modeLabel = document.getElementById('pause-mode-label');
        const activeCls = ['bg-white', 'shadow', 'text-slate-800'];
        const idleCls = ['text-slate-400'];

        if (autoBtn && manualBtn) {
            const isAuto = mode === 'auto';
            autoBtn.classList.remove(...activeCls, ...idleCls);
            manualBtn.classList.remove(...activeCls, ...idleCls);
            autoBtn.classList.add(...(isAuto ? activeCls : idleCls));
            manualBtn.classList.add(...(isAuto ? idleCls : activeCls));
        }
        if (modeLabel) {
            modeLabel.innerText = mode === 'auto' ? 'AUTO' : 'MANUAL';
        }
    }

    function applyPauseResponse(data) {
        const cid = data.counter_id ? String(data.counter_id) : '';
        if (cid) {
            if (counterState[cid]) {
                counterState[cid].queue_paused = !!data.queue_paused;
                if (data.pause_mode) {
                    counterState[cid].auto_pause_enabled = data.pause_mode === 'auto';
                    if (data.pause_mode === 'manual') counterState[cid].lunch_break_paused = false;
                }
            }
        } else {
            departmentIsPaused = !!data.queue_paused;
            if (data.pause_mode) departmentPauseMode = data.pause_mode;
            departmentPauseSource = 'manual';
        }
        refreshPauseStateUI();
    }

    function setCounterStates(counters) {
        if (!Array.isArray(counters)) return;
        counters.forEach(function (c) {
            const key = String(c.id);
            counterState[key] = {
                queue_paused: !!c.queue_paused,
                auto_pause_enabled: !!c.auto_pause_enabled,
                lunch_break_paused: !!c.lunch_break_paused,
            };
        });
    }

    function effectivePauseState() {
        const cid = currentCounterId();
        const state = cid ? counterState[cid] : null;
        if (state) {
            const windowPaused = !!state.queue_paused;
            return {
                paused: windowPaused || departmentIsPaused,
                mode: state.auto_pause_enabled ? 'auto' : 'manual',
                source: windowPaused
                    ? (state.lunch_break_paused ? 'lunch' : 'manual')
                    : departmentPauseSource,
                scope: windowPaused ? 'window' : 'department',
            };
        }
        return {
            paused: departmentIsPaused,
            mode: departmentPauseMode,
            source: departmentPauseSource,
            scope: 'department',
        };
    }

    function refreshPauseStateUI(lbStart = null, lbEnd = null) {
        const state = effectivePauseState();
        pauseScope = state.scope;
        updatePauseResumeUI(state.paused, lbStart, lbEnd, state.source, state.mode);
        updateCounterPauseBadges();
    }

    function updateCounterPauseBadges() {
        document.querySelectorAll('[data-counter-card]').forEach(card => {
            const cid = String(card.getAttribute('data-counter-card'));
            const state = counterState[cid];
            const status = card.querySelector(`[data-counter-status="${cid}"]`);
            if (!state || !status) return;
            if (state.queue_paused) {
                status.innerText = 'PAUSED';
                status.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-100 text-orange-600';
            } else if (status.innerText === 'PAUSED') {
                const busy = !!card.querySelector('.ticket-lg');
                if (busy) {
                    status.innerText = 'SERVING';
                    status.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700';
                } else {
                    status.innerText = 'IDLE';
                    status.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-400';
                }
            }
        });
    }

    async function sendPauseMode(mode) {
        if (IS_ADMIN_VIEWER) return;
        if (!SELECTED_DEPARTMENT_ID) return;
        if (mode !== 'auto' && mode !== 'manual') return;

        try {
            const res = await fetch(PAUSE_MODE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(Object.assign(
                    { mode, department_id: SELECTED_DEPARTMENT_ID },
                    currentCounterId() ? { counter_id: Number(currentCounterId()) } : {}
                )),
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Unable to update pause mode.');
            }

            applyPauseResponse(data);
            showToast(data.message, 'success');
            if (typeof refreshDashboard === 'function') refreshDashboard();
        } catch (e) {
            showToast(e.message || 'Failed to update pause mode.', 'warning');
        }
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

    // ── Window selection + focus/fullscreen mode ─────────────────────────────
    const WINDOW_STORAGE_KEY = 'queue.window.' + (SELECTED_DEPARTMENT_ID || 'unknown');
    let focusedWindowId = null;
    const windowSelect = document.getElementById('window-select');
    const focusModeBtn = document.getElementById('focus-mode-btn');
    const exitFocusBtn = document.getElementById('exit-focus-btn');

    function counterIds() {
        if (!windowSelect) return [];
        return Array.from(windowSelect.options).map(option => option.value).filter(Boolean);
    }

    function applyWindowSelection(value) {
        const selected = String(value || '');
        focusedWindowId = selected || null;

        document.querySelectorAll('[data-counter-card]').forEach(card => {
            card.classList.toggle(
                'focused-window',
                focusedWindowId && String(card.getAttribute('data-counter-card')) === focusedWindowId
            );
        });

        if (focusModeBtn) focusModeBtn.disabled = !focusedWindowId;

        try {
            if (focusedWindowId) localStorage.setItem(WINDOW_STORAGE_KEY, focusedWindowId);
            else localStorage.removeItem(WINDOW_STORAGE_KEY);
        } catch (error) { /* storage unavailable */ }

        if (!focusedWindowId) exitFocusMode();

        refreshPauseStateUI();
    }

    function currentCounterId() {
        const selected = focusedWindowId || (windowSelect ? windowSelect.value : '') || '';
        return String(selected || '');
    }

    function currentCounterName() {
        if (!windowSelect) return '';
        const option = windowSelect.options[windowSelect.selectedIndex];
        return option ? option.textContent.trim() : '';
    }

    function restoreWindowSelection() {
        if (!windowSelect) return;

        let saved = '';
        try { saved = localStorage.getItem(WINDOW_STORAGE_KEY) || ''; } catch (error) { saved = ''; }

        if (saved && counterIds().includes(saved)) windowSelect.value = saved;
        applyWindowSelection(windowSelect.value);
    }

    function enterFocusMode() {
        if (!windowSelect || !windowSelect.value) {
            showToast('Select your window first.', 'warning');
            return;
        }

        document.body.classList.add('focus-mode');

        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(function () {});
        }
    }

    function exitFocusMode() {
        document.body.classList.remove('focus-mode');

        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
        }
    }

    if (windowSelect) {
        windowSelect.addEventListener('change', function () {
            applyWindowSelection(windowSelect.value);
        });
    }
    if (focusModeBtn) focusModeBtn.addEventListener('click', enterFocusMode);
    if (exitFocusBtn) exitFocusBtn.addEventListener('click', exitFocusMode);
    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) document.body.classList.remove('focus-mode');
    });

    restoreWindowSelection();

    refreshPauseStateUI();
    updateAutoSkipTimer();
    setInterval(updateAutoSkipTimer, 1000);
</script>
@endsection
