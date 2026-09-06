@extends('layouts.app')
@section('page-title', 'Admin Overview')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-6">
    <div>
        <div class="text-[11px] font-semibold text-primary uppercase tracking-[0.2em]">Operations center</div>
        <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-slate-900 mt-1">Admin Overview</h1>
        <p class="text-sm text-slate-500 mt-1">A live view of every department queue and service point.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-green-500" id="overview-live-dot"></span>
            <span id="overview-updated">Updated {{ \Carbon\Carbon::parse($overview['updated_at'])->format('g:i A') }}</span>
        </span>
        <a href="{{ route('admin.departments.index') }}" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-700 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors">
            <i class="bi bi-gear"></i> Manage
        </a>
    </div>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4 mb-6">
    @php
        $overviewStats = [
            ['key' => 'waiting', 'label' => 'Waiting now', 'icon' => 'hourglass-split', 'tone' => 'yellow', 'value' => $overview['today']['waiting']],
            ['key' => 'serving', 'label' => 'Currently serving', 'icon' => 'person-fill', 'tone' => 'blue', 'value' => $overview['today']['serving']],
            ['key' => 'completed', 'label' => 'Completed today', 'icon' => 'check-circle-fill', 'tone' => 'green', 'value' => $overview['today']['completed']],
            ['key' => 'no_response', 'label' => 'No response today', 'icon' => 'x-circle-fill', 'tone' => 'red', 'value' => $overview['today']['no_response']],
        ];
    @endphp
    @foreach($overviewStats as $stat)
        <div class="bg-white rounded-2xl border border-slate-200 p-4 lg:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">{{ $stat['label'] }}</div>
                    <div class="text-3xl font-black text-slate-900 mt-1" id="overview-stat-{{ $stat['key'] }}">{{ $stat['value'] }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-{{ $stat['tone'] }}-50 text-{{ $stat['tone'] }}-600 flex items-center justify-center shrink-0">
                    <i class="bi bi-{{ $stat['icon'] }} text-lg"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <section class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Department overview</h2>
                <p class="text-xs text-slate-400 mt-0.5">Current queue load and service availability.</p>
            </div>
            <span class="text-xs font-semibold text-slate-400">{{ count($overview['departments']) }} departments</span>
        </div>
        <div id="department-overview-grid" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4">
            @forelse($overview['departments'] as $department)
                @include('admin.partials.department-overview-card', ['department' => $department])
            @empty
                <div class="md:col-span-2 text-center py-12 text-sm text-slate-400">No departments configured.</div>
            @endforelse
        </div>
    </section>

    <aside class="space-y-4">
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">Alerts</h2>
            </div>
            <div id="overview-alerts" class="p-4 space-y-2">
                @forelse($overview['alerts'] as $alert)
                    <div class="flex items-start gap-2.5 rounded-xl px-3 py-2.5 {{ $alert['type'] === 'danger' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700' }}">
                        <i class="bi {{ $alert['type'] === 'danger' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle' }} mt-0.5"></i>
                        <span class="text-xs font-semibold">{{ $alert['message'] }}</span>
                    </div>
                @empty
                    <div class="flex items-center gap-2 text-sm text-green-700 bg-green-50 rounded-xl px-3 py-3">
                        <i class="bi bi-check-circle-fill"></i> All departments are operational.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-900">System health</h2>
            </div>
            <div class="p-4 space-y-3">
                @foreach(['sms' => 'SMS notifications', 'realtime' => 'Real-time updates', 'queue' => 'Background queue'] as $key => $label)
                    @php $integration = $overview['integrations'][$key]; @endphp
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-slate-600">{{ $label }}</span>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold {{ $integration['ready'] ? 'text-green-700 bg-green-50' : 'text-yellow-700 bg-yellow-50' }} rounded-full px-2.5 py-1">
                            <span class="w-1.5 h-1.5 rounded-full {{ $integration['ready'] ? 'bg-green-500' : 'bg-yellow-500' }}"></span>
                            {{ $integration['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    </aside>
</div>

<section class="bg-slate-900 rounded-2xl p-5 lg:p-6 text-white">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-blue-300">Quick actions</div>
            <h2 class="text-lg font-black mt-1">Manage the operation</h2>
            <p class="text-sm text-slate-400 mt-1">Open a queue console or manage the configuration behind it.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($overview['departments']->isNotEmpty())
                <a href="{{ route('admin.queue', ['department_id' => $overview['departments'][0]['id']]) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors">
                    <i class="bi bi-display"></i> Open Queue Console
                </a>
            @endif
            <a href="{{ route('admin.departments.index') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors">
                <i class="bi bi-building"></i> Departments & Staff
            </a>
            <a href="{{ route('admin.reports') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
    const OVERVIEW_STATUS_URL = @json(route('admin.overview.status'));

    function escapeOverviewHtml(value) {
        const element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function departmentCard(department) {
        const state = !department.is_active
            ? {label: 'INACTIVE', classes: 'bg-slate-100 text-slate-500', dot: 'bg-slate-400'}
            : department.queue_paused
                ? {label: 'PAUSED', classes: 'bg-yellow-50 text-yellow-700', dot: 'bg-yellow-500'}
                : {label: 'LIVE', classes: 'bg-green-50 text-green-700', dot: 'bg-green-500'};
        const current = department.current_ticket || 'Waiting';

        return `<article class="rounded-xl border border-slate-200 p-4 hover:border-primary/40 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">${escapeOverviewHtml(department.name)}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">${department.active_staff_count}/${department.staff_count} active staff</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold rounded-full px-2 py-1 ${state.classes}"><span class="w-1.5 h-1.5 rounded-full ${state.dot}"></span>${state.label}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-4">
                <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Serving</div><div class="text-base font-black text-primary mt-0.5">${escapeOverviewHtml(current)}</div></div>
                <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Waiting</div><div class="text-base font-black text-yellow-600 mt-0.5">${department.waiting_count}</div></div>
                <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Est. wait</div><div class="text-base font-black text-slate-700 mt-0.5">${department.estimated_wait_minutes}m</div></div>
            </div>
            <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                <span class="text-[11px] text-slate-400">${department.completed_count} completed · ${department.no_response_count} no response</span>
                <a href="/admin/queue?department_id=${encodeURIComponent(department.id)}" class="text-[11px] font-bold text-primary hover:text-primary-dark">Open console <i class="bi bi-arrow-right"></i></a>
            </div>
        </article>`;
    }

    function renderOverview(data) {
        const stats = data.today || {};
        ['waiting', 'serving', 'completed', 'no_response'].forEach(key => {
            const element = document.getElementById(`overview-stat-${key}`);
            if (element) element.innerText = stats[key] ?? 0;
        });

        const grid = document.getElementById('department-overview-grid');
        if (grid) {
            grid.innerHTML = data.departments?.length
                ? data.departments.map(departmentCard).join('')
                : '<div class="md:col-span-2 text-center py-12 text-sm text-slate-400">No departments configured.</div>';
        }

        const updated = document.getElementById('overview-updated');
        if (updated) updated.innerText = `Updated ${new Date(data.updated_at).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})}`;
    }

    async function refreshOverview() {
        try {
            const response = await fetch(OVERVIEW_STATUS_URL, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Unable to refresh overview');
            renderOverview(await response.json());
        } catch (error) {
            const dot = document.getElementById('overview-live-dot');
            if (dot) dot.className = 'w-2 h-2 rounded-full bg-yellow-500';
        }
    }

    setInterval(refreshOverview, 10000);
</script>
@endsection
