@extends('layouts.app')
@section('page-title', 'Queue Status')
@section('content')
{{-- Fix #2: Full-width guest layout, not squeezed into auth-wrap --}}
<div class="w-full max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="text-center mb-8">
        <div class="w-20 h-20 mx-auto mb-4">
            <img src="/1973802-removebg-preview.png" alt="ACLC Logo" class="w-full h-full object-contain">
        </div>
        <h1 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">ACLC Mandaue Queue</h1>
        <p class="text-slate-400 text-sm mt-1">Virtual Queue System</p>
        <form action="{{ route('home') }}" method="GET" class="mt-4 flex justify-center">
            <label for="public-department" class="sr-only">Department</label>
            <select id="public-department" name="department_id" onchange="this.form.submit()"
                class="min-w-52 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected($selectedDepartment?->id === $department->id)>{{ $department->name }}</option>
                @endforeach
                @if($departments->isEmpty())
                    <option value="" selected disabled>No active departments</option>
                @endif
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

        {{-- Live Numbers --}}
        <div class="md:col-span-3 flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-10 text-center">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Now Serving</div>
                <div class="ticket-xl text-primary" id="current-number">{{ $currentNumber }}</div>
                <div class="mt-4">
                    <span id="public-queue-status" class="{{ $queuePaused ? 'bg-yellow-50 text-yellow-700' : 'badge-live bg-green-50 text-green-700' }} inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full">
                        <span class="w-1.5 h-1.5 {{ $queuePaused ? 'bg-yellow-500' : 'bg-green-500' }} rounded-full"></span>
                        {{ $queuePaused ? 'ON BREAK' : 'LIVE' }}
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
                            {{ $queuePaused ? 'On Break' : ($waitingCount > 0 ? '~' . round($waitingCount * $avgServeTime) . ' min' : 'Ready') }}
                        </div>
                        <div class="text-[11px] font-semibold text-blue-400 mt-0.5" id="public-avg-label">Est. Wait (avg {{ $avgServeTime }} min/ticket)</div>
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
                <a href="{{ $selectedDepartment ? route('register', ['department_id' => $selectedDepartment->id]) : '#' }}"
                    class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-3 rounded-xl transition-colors {{ $selectedDepartment ? '' : 'opacity-40 pointer-events-none' }}">
                    <i class="bi bi-phone"></i> Verify Phone to Get a Ticket
                </a>
                {{-- <a href="{{ route('register') }}"
                    class="w-full flex items-center justify-center gap-2 border border-slate-200 text-slate-600 font-semibold text-sm px-4 py-3 rounded-xl hover:bg-slate-50 transition-colors">
                    <i class="bi bi-person-plus"></i> Create Account
                </a> --}}
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
const departmentId = {{ $selectedDepartment?->id ?? 'null' }};
function renderPublicQueue(data) {
    const current = document.getElementById('current-number');
    const next = document.getElementById('next-number');
    const waiting = document.getElementById('waiting-count');
    const estimate = document.getElementById('est-wait-time');
    const averageLabel = document.getElementById('public-avg-label');
    const status = document.getElementById('public-queue-status');
    const average = Number(data.avg_serve_mins ?? {{ $avgServeTime }});
    const paused = Boolean(data.queue_paused);

    if (current && data.current !== undefined) current.innerText = data.current;
    if (next && data.next !== undefined) next.innerText = data.next;
    if (waiting && data.waiting_count !== undefined) waiting.innerText = data.waiting_count;
    if (estimate && data.waiting_count !== undefined) {
        estimate.innerText = paused
            ? 'On Break'
            : (data.waiting_count > 0 ? `~${Math.round(data.waiting_count * average)} min` : 'Ready');
    }
    if (averageLabel) averageLabel.innerText = `Est. Wait (avg ${average} min/ticket)`;
    if (status) {
        status.className = `${paused ? 'bg-yellow-50 text-yellow-700' : 'badge-live bg-green-50 text-green-700'} inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full`;
        status.innerHTML = `<span class="w-1.5 h-1.5 ${paused ? 'bg-yellow-500' : 'bg-green-500'} rounded-full"></span>${paused ? 'ON BREAK' : 'LIVE'}`;
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

if (window.Echo && departmentId) {
    window.Echo.channel(`queue.${departmentId}`).listen('.queue.updated', renderPublicQueue);
}

refreshPublicQueue();
setInterval(refreshPublicQueue, 5000);
</script>
@endsection
