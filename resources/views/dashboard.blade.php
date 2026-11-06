@extends('layouts.app')
@section('page-title', 'Queue Status')
@section('content')
{{-- Fix #2: Full-width guest layout, not squeezed into auth-wrap --}}
<div class="w-full max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="text-center mb-6">
        <div class="w-20 h-20 mx-auto mb-4">
            <img src="/1973802-removebg-preview.png" alt="ACLC Logo" class="w-full h-full object-contain">
        </div>
        <h1 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">ACLC Mandaue Queue</h1>
        <p class="text-slate-400 text-sm mt-1">Virtual Queue System</p>

        <div class="mt-5 flex flex-wrap justify-center gap-2" id="department-chips">
            @foreach($departments as $department)
                <a href="{{ route('home', ['department_id' => $department->id]) }}"
                    class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors {{ $selectedDepartment?->id === $department->id ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-primary/40 hover:text-primary' }}">
                    {{ $department->name }}
                </a>
            @endforeach
            @if($departments->isEmpty())
                <span class="px-4 py-2 rounded-full text-sm font-semibold border border-dashed border-slate-300 text-slate-400">No active departments</span>
            @endif
        </div>
    </div>

    {{-- Pause / lunch break banner --}}
    <div id="pause-banner"
        class="mb-4 rounded-2xl border px-4 py-3 text-sm font-semibold flex items-center justify-center gap-2.5 text-center
            {{ $queuePaused ? ($pauseSource === 'lunch' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-yellow-50 border-yellow-200 text-yellow-700') : 'hidden' }}">
        <i class="bi bi-clock-history"></i>
        <span id="pause-banner-text">
            @if($queuePaused)
                {{ $pauseSource === 'lunch' ? 'Lunch break — queues resume at '.$lunchEnd : 'Queue is temporarily paused — please wait' }}
            @else
                Queue is temporarily paused — please wait
            @endif
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

        {{-- Live Numbers --}}
        <div class="md:col-span-3 flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-10 text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Now Serving</div>

                <div id="serving-single">
                    <div class="ticket-xl text-primary" id="current-number">{{ $currentNumber }}</div>
                </div>
                <div id="serving-multi" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-3"></div>

                <div class="mt-4 flex flex-wrap items-center justify-center gap-2.5">
                    <span id="public-queue-status" class="{{ $queuePaused ? 'bg-yellow-50 text-yellow-700' : 'badge-live bg-green-50 text-green-700' }} inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full">
                        <span class="w-1.5 h-1.5 {{ $queuePaused ? 'bg-yellow-500' : 'bg-green-500' }} rounded-full"></span>
                        {{ $queuePaused ? 'ON BREAK' : 'LIVE' }}
                    </span>
                    <span id="serving-windows" class="text-xs font-semibold text-slate-400">
                        {{ $servingCount > 0 ? $servingCount.' window'.($servingCount > 1 ? 's' : '').' serving' : '' }}
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Next in Line</div>
                <div class="ticket-lg text-slate-400" id="next-number">{{ $nextNumber }}</div>
                <div class="text-xs text-slate-400 mt-2">Please prepare your requirements</div>

                <div class="mt-4 pt-4 border-t border-dashed border-slate-200">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5">Up Next</div>
                    <div class="flex flex-wrap justify-center gap-1.5" id="waiting-list">
                        @forelse($waitingList as $entry)
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 text-[11px] font-bold">{{ $entry->ticket_number }}</span>
                        @empty
                            <span class="text-xs text-slate-300 font-semibold">No one in line yet</span>
                        @endforelse
                    </div>
                </div>
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
                            {{ $queuePaused ? 'On Break' : ($waitingCount > 0 ? '~' . round($waitingCount * $avgServeTime) . ' min' : 'Ready') }}
                        </div>
                        <div class="text-[11px] font-semibold text-blue-400 mt-0.5" id="public-avg-label">Est. Wait (avg {{ $avgServeTime }} min/ticket)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CTA + How it works --}}
        <div class="md:col-span-2 flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col flex-1">
                <div class="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-white text-2xl mx-auto mb-4">
                    <i class="bi bi-ticket-perforated-fill"></i>
                </div>
                <h2 class="text-lg font-bold text-slate-800 text-center mb-2">Virtual Ticketing</h2>
                <p class="text-sm text-slate-400 text-center mb-6 leading-relaxed">
                    Skip the line. Get a virtual ticket and we'll notify you via SMS when it's your turn.
                </p>
                <div class="flex flex-col gap-2 mt-auto">
                    <a href="{{ $selectedDepartment ? route('register', ['department_id' => $selectedDepartment->id]) : '#' }}"
                        class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors {{ $selectedDepartment ? '' : 'opacity-40 pointer-events-none' }}">
                        <i class="bi bi-phone"></i> Verify Phone to Get a Ticket
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4 text-center">How it works</div>
                <ol class="flex flex-col gap-3.5">
                    @foreach([
                        ['Verify your phone', 'One-time SMS OTP code'],
                        ['Get your ticket', 'A ticket number is issued instantly'],
                        ['Get notified', "We'll text you when it's your turn"],
                        ['Go to your window', 'Show your ticket at the assigned counter'],
                    ] as $index => $step)
                        <li class="flex gap-3 items-start">
                            <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">{{ $index + 1 }}</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-700 leading-tight">{{ $step[0] }}</p>
                                <p class="text-xs text-slate-400 mt-0.5 leading-snug">{{ $step[1] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
const departmentId = {{ $selectedDepartment?->id ?? 'null' }};
const lunchEnd = @json($lunchEnd);

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
}

function renderPublicQueue(data) {
    const current = document.getElementById('current-number');
    const single = document.getElementById('serving-single');
    const multi = document.getElementById('serving-multi');
    const windows = document.getElementById('serving-windows');
    const next = document.getElementById('next-number');
    const waiting = document.getElementById('waiting-count');
    const estimate = document.getElementById('est-wait-time');
    const averageLabel = document.getElementById('public-avg-label');
    const status = document.getElementById('public-queue-status');
    const list = document.getElementById('waiting-list');
    const banner = document.getElementById('pause-banner');
    const bannerText = document.getElementById('pause-banner-text');
    const average = Number(data.avg_serve_mins ?? {{ $avgServeTime }});
    const paused = Boolean(data.queue_paused);
    const servings = Array.isArray(data.current_servings) ? data.current_servings : [];

    if (single && multi) {
        if (servings.length > 1) {
            single.classList.add('hidden');
            multi.classList.remove('hidden');
            multi.innerHTML = servings.map(item => `
                <div class="rounded-xl bg-primary/5 border border-primary/10 p-3 text-center">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">${escapeHtml(item.counter_name || 'Window')}</div>
                    <div class="ticket-lg text-primary mt-1">${escapeHtml(item.ticket_number || '--')}</div>
                </div>`).join('');
        } else {
            multi.classList.add('hidden');
            multi.innerHTML = '';
            single.classList.remove('hidden');
            if (current && data.current !== undefined) current.innerText = data.current;
        }
    }

    if (windows) {
        const count = Number(data.serving_count ?? servings.length);
        windows.innerText = count > 0 ? `${count} window${count > 1 ? 's' : ''} serving` : '';
    }

    if (next && data.next !== undefined) next.innerText = data.next;
    if (waiting && data.waiting_count !== undefined) waiting.innerText = data.waiting_count;
    if (estimate && data.waiting_count !== undefined) {
        estimate.innerText = paused
            ? 'On Break'
            : (data.waiting_count > 0 ? `~${Math.round(data.waiting_count * average)} min` : 'Ready');
    }
    if (averageLabel) averageLabel.innerText = `Est. Wait (avg ${average} min/ticket)`;

    if (list && Array.isArray(data.waiting_list)) {
        list.innerHTML = data.waiting_list.length
            ? data.waiting_list.map(item =>
                `<span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 text-[11px] font-bold">${escapeHtml(item.ticket_number)}</span>`
            ).join('')
            : '<span class="text-xs text-slate-300 font-semibold">No one in line yet</span>';
    }

    if (status) {
        status.className = `${paused ? 'bg-yellow-50 text-yellow-700' : 'badge-live bg-green-50 text-green-700'} inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full`;
        status.innerHTML = `<span class="w-1.5 h-1.5 ${paused ? 'bg-yellow-500' : 'bg-green-500'} rounded-full"></span>${paused ? 'ON BREAK' : 'LIVE'}`;
    }

    if (banner && bannerText) {
        if (paused) {
            const isLunch = data.pause_source === 'lunch';
            banner.className = `mb-4 rounded-2xl border px-4 py-3 text-sm font-semibold flex items-center justify-center gap-2.5 text-center ${isLunch ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-yellow-50 border-yellow-200 text-yellow-700'}`;
            bannerText.innerText = isLunch
                ? `Lunch break — queues resume at ${lunchEnd || '13:30'}`
                : 'Queue is temporarily paused — please wait';
        } else {
            banner.className = 'hidden';
        }
    }
}

async function refreshPublicQueue() {
    if (!departmentId) return;
    const statusUrl = new URL('{{ route("api.queueStatus") }}', window.location.origin);
    statusUrl.searchParams.set('department_id', departmentId);

    try {
        const response = await fetch(statusUrl);
        if (response.ok) renderPublicQueue(await response.json());
    } catch (error) {
        // Keep the last known state and retry on the next polling interval.
    }
}

var echoAttached = false;
function attachRealtime() {
    if (echoAttached || !window.Echo || !departmentId) return;
    echoAttached = true;
    window.Echo.channel(`queue.${departmentId}`).listen('.queue.updated', renderPublicQueue);
}
attachRealtime();
window.addEventListener('echo:ready', attachRealtime);

refreshPublicQueue();
setInterval(refreshPublicQueue, 5000);
</script>
@endsection
