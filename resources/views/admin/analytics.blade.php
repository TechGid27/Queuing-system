@extends('layouts.app')
@section('page-title', 'Queue Analytics')

@section('content')
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
    <div>
        <div class="text-[11px] font-semibold text-primary uppercase tracking-[0.2em]">Insights</div>
        <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-slate-900 mt-1">Queue Analytics</h1>
        <p class="text-sm text-slate-500 mt-1">Ticket volume, service time, and department load across the selected period.</p>
        <p class="text-xs text-slate-400 mt-1">{{ $filters['range'] === 'custom' ? 'Custom range' : 'Selected period' }}: <span id="analytics-range-label">--</span></p>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
        <form id="analytics-filters" method="GET" action="{{ route('admin.analytics') }}" class="flex flex-col sm:flex-row sm:flex-wrap gap-2 w-full lg:w-auto">
            <label for="analytics-range" class="sr-only">Date range</label>
            <select id="analytics-range" name="range"
                class="w-full sm:w-auto px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="today" @selected($filters['range'] === 'today')>Today</option>
                <option value="7d" @selected($filters['range'] === '7d')>Last 7 days</option>
                <option value="30d" @selected($filters['range'] === '30d')>Last 30 days</option>
                <option value="custom" @selected($filters['range'] === 'custom')>Custom range</option>
            </select>

            <label for="analytics-department" class="sr-only">Department</label>
            <select id="analytics-department" name="department_id"
                class="w-full sm:w-auto px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>

            <div id="custom-range" class="{{ $filters['range'] === 'custom' ? 'flex' : 'hidden' }} flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <input type="date" id="analytics-from" name="from" value="{{ $filters['from'] }}" aria-label="From date"
                    class="px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <span class="hidden sm:inline text-xs font-semibold text-slate-400">to</span>
                <input type="date" id="analytics-to" name="to" value="{{ $filters['to'] }}" aria-label="To date"
                    class="px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>

            <button type="submit"
                class="inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors">
                <i class="bi bi-funnel"></i> Apply
            </button>
        </form>

        <button type="button" id="analytics-refresh"
            class="inline-flex items-center justify-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 rounded-xl transition-colors">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<div id="analytics-error" class="hidden mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>

<div id="analytics-empty" class="hidden mb-5 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center">
    <i class="bi bi-inbox text-3xl text-slate-300"></i>
    <p class="text-sm font-semibold text-slate-600 mt-2">No tickets found for this period.</p>
    <p class="text-xs text-slate-400 mt-1">Try a wider date range or a different department.</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Total tickets</div>
                <div class="text-2xl lg:text-3xl font-black text-slate-900 mt-1 truncate" id="kpi-total">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <i class="bi bi-ticket-perforated"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Completed</div>
                <div class="text-2xl lg:text-3xl font-black text-green-600 mt-1 truncate" id="kpi-completed">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">No response</div>
                <div class="text-2xl lg:text-3xl font-black text-red-600 mt-1 truncate" id="kpi-no-response">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <i class="bi bi-x-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Avg wait</div>
                <div class="text-2xl lg:text-3xl font-black text-yellow-600 mt-1 truncate" id="kpi-avg-wait">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center shrink-0">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Avg service</div>
                <div class="text-2xl lg:text-3xl font-black text-blue-600 mt-1 truncate" id="kpi-avg-service">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i class="bi bi-stopwatch"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">No-show rate</div>
                <div class="text-2xl lg:text-3xl font-black text-slate-900 mt-1 truncate" id="kpi-no-show-rate">--</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <i class="bi bi-percent"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Hourly queue volume</h2>
                <p class="text-xs text-slate-400 mt-0.5">Tickets created per hour.</p>
            </div>
            <span class="text-[11px] font-semibold text-slate-400" id="analytics-updated">Updated --</span>
        </div>
        <div class="relative w-full h-[240px] sm:h-[300px] lg:h-[320px] p-4">
            <canvas id="chart-hourly" role="img" aria-label="Bar chart of tickets created per hour"></canvas>
        </div>
    </section>

    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden min-w-0">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900">Status mix</h2>
            <p class="text-xs text-slate-400 mt-0.5">Completed, no response (incl. skipped), waiting, and serving.</p>
        </div>
        <div class="relative w-full h-[240px] sm:h-[280px] lg:h-[300px] p-4">
            <canvas id="chart-status" role="img" aria-label="Doughnut chart of ticket status distribution"></canvas>
        </div>
    </section>

    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden min-w-0">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900">Wait vs service time</h2>
            <p class="text-xs text-slate-400 mt-0.5">Daily averages in minutes (right axis) and ticket counts (left axis).</p>
        </div>
        <div class="relative w-full h-[240px] sm:h-[280px] lg:h-[300px] p-4">
            <canvas id="chart-trend" role="img" aria-label="Line chart of daily wait and service time and ticket counts"></canvas>
        </div>
    </section>

    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden min-w-0">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900">Department comparison</h2>
            <p class="text-xs text-slate-400 mt-0.5">Completed, no response, and pending tickets per department.</p>
        </div>
        <div class="relative w-full h-[260px] sm:h-[320px] lg:h-[360px] p-4">
            <canvas id="chart-departments" role="img" aria-label="Stacked bar chart comparing departments"></canvas>
        </div>
    </section>

    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden min-w-0">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900">Purpose mix</h2>
            <p class="text-xs text-slate-400 mt-0.5">Most requested services in the selected period.</p>
        </div>
        <div class="relative w-full h-[260px] sm:h-[320px] lg:h-[340px] p-4">
            <canvas id="chart-purposes" role="img" aria-label="Bar chart of requests by purpose"></canvas>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chart.js/chart.umd.min.js') }}"></script>
<script>
(function () {
    const endpoint = @json(route('admin.analytics.data'));
    const charts = {};
    const colors = {
        primary: '#2563eb',
        green: '#16a34a',
        red: '#dc2626',
        yellow: '#d97706',
        slate: '#64748b',
        blue: '#0ea5e9',
        purple: '#7c3aed'
    };

    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.18)';
    }

    function isMobile() {
        return window.matchMedia('(max-width: 640px)').matches;
    }

    function chartOptions(extra) {
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 300 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 8,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 12,
                        font: { size: 11, weight: '600' }
                    }
                }
            }
        }, extra || {});
    }

    function destroyCharts() {
        Object.keys(charts).forEach(function (key) {
            try { charts[key].destroy(); } catch (error) { /* ignore */ }
            delete charts[key];
        });
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    function formatMinutes(value) {
        return value === null || value === undefined ? '--' : value + 'm';
    }

    function showError(message) {
        const element = document.getElementById('analytics-error');
        if (!element) return;
        element.textContent = message;
        element.classList.remove('hidden');
    }

    function hideError() {
        const element = document.getElementById('analytics-error');
        if (element) element.classList.add('hidden');
    }

    function renderKpis(data) {
        setText('kpi-total', data.total);
        setText('kpi-completed', data.completed);
        setText('kpi-no-response', data.no_response);
        setText('kpi-avg-wait', formatMinutes(data.avg_wait_minutes));
        setText('kpi-avg-service', formatMinutes(data.avg_service_minutes));
        setText('kpi-no-show-rate', data.no_show_rate + '%');
    }

    function renderHourly(data) {
        const canvas = document.getElementById('chart-hourly');
        if (!canvas) return;

        charts.hourly = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Tickets',
                    data: data.data,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    hoverBackgroundColor: colors.primary,
                    borderRadius: 5,
                    maxBarThickness: 26
                }]
            },
            options: chartOptions({
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: isMobile() ? 8 : 12, maxRotation: 0, autoSkip: true, font: { size: 10 } }
                    },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } }
                }
            })
        });
    }

    function renderStatuses(data) {
        const canvas = document.getElementById('chart-status');
        if (!canvas) return;

        charts.status = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: [colors.green, colors.red, colors.yellow, colors.primary],
                    borderWidth: 0
                }]
            },
            options: chartOptions({
                cutout: '62%',
                plugins: {
                    legend: chartOptions().plugins.legend,
                    tooltip: { callbacks: { label: function (context) {
                        const total = context.dataset.data.reduce(function (sum, value) { return sum + value; }, 0);
                        const percent = total ? Math.round((context.parsed / total) * 100) : 0;
                        return ' ' + context.label + ': ' + context.parsed + ' (' + percent + '%)';
                    } } }
                }
            })
        });
    }

    function renderTrend(data) {
        const canvas = document.getElementById('chart-trend');
        if (!canvas) return;

        charts.trend = new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Tickets',
                        data: data.total,
                        borderColor: colors.slate,
                        backgroundColor: 'rgba(100, 116, 139, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: isMobile() ? 0 : 2,
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Completed',
                        data: data.completed,
                        borderColor: colors.green,
                        backgroundColor: 'rgba(22, 163, 74, 0.06)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: isMobile() ? 0 : 2,
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Avg wait (min)',
                        data: data.avg_wait,
                        borderColor: colors.yellow,
                        borderDash: [5, 4],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                        spanGaps: true,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Avg service (min)',
                        data: data.avg_service,
                        borderColor: colors.primary,
                        borderDash: [2, 3],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                        spanGaps: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: chartOptions({
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: isMobile() ? 6 : 10, maxRotation: 0, autoSkip: true, font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: { precision: 0, font: { size: 10 } },
                        title: { display: true, text: 'Tickets', font: { size: 10 } }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 10 } },
                        title: { display: true, text: 'Minutes', font: { size: 10 } }
                    }
                }
            })
        });
    }

    function renderDepartments(data) {
        const canvas = document.getElementById('chart-departments');
        if (!canvas) return;

        charts.departments = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    { label: 'Completed', data: data.completed, backgroundColor: colors.green, borderRadius: 4 },
                    { label: 'No response', data: data.no_response, backgroundColor: colors.red, borderRadius: 4 },
                    { label: 'Pending', data: data.pending, backgroundColor: colors.yellow, borderRadius: 4 }
                ]
            },
            options: chartOptions({
                indexAxis: 'y',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 8, usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11, weight: '600' } }
                    }
                },
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
                    y: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            })
        });
    }

    function renderPurposes(data) {
        const canvas = document.getElementById('chart-purposes');
        if (!canvas) return;

        charts.purposes = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Requests',
                    data: data.data,
                    backgroundColor: 'rgba(124, 58, 237, 0.72)',
                    hoverBackgroundColor: colors.purple,
                    borderRadius: 4,
                    maxBarThickness: 24
                }]
            },
            options: chartOptions({
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
                    y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            })
        });
    }

    function render(data) {
        destroyCharts();
        hideError();

        const empty = document.getElementById('analytics-empty');
        if (empty) empty.classList.toggle('hidden', data.kpis.total !== 0);

        setText('analytics-range-label', data.range.label);
        setText('analytics-updated', 'Updated ' + new Date(data.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

        renderKpis(data.kpis);
        renderHourly(data.hourly);
        renderStatuses(data.statuses);
        renderTrend(data.trend);
        renderDepartments(data.departments);
        renderPurposes(data.purposes);
    }

    function currentParams() {
        const form = document.getElementById('analytics-filters');
        const params = new URLSearchParams(new FormData(form));

        if (params.get('range') !== 'custom') {
            params.delete('from');
            params.delete('to');
        }

        return params;
    }

    function syncUrl(data) {
        const params = currentParams();
        const fromInput = document.getElementById('analytics-from');
        const toInput = document.getElementById('analytics-to');

        if (params.get('range') === 'custom') {
            if (fromInput) fromInput.value = data.range.from;
            if (toInput) toInput.value = data.range.to;
            params.set('from', data.range.from);
            params.set('to', data.range.to);
        }

        const query = params.toString();
        history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    function validationMessage(payload) {
        if (!payload || !payload.errors) return null;

        const messages = Object.keys(payload.errors).reduce(function (all, key) {
            return all.concat(payload.errors[key] || []);
        }, []);

        return messages.length ? messages.join(' ') : null;
    }

    async function loadAnalytics() {
        const button = document.getElementById('analytics-refresh');
        if (button) button.disabled = true;

        try {
            const params = currentParams();
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            const payload = await response.json().catch(function () { return null; });

            if (!response.ok) {
                throw new Error(validationMessage(payload) || 'Unable to load analytics data. Check the date range and try again.');
            }

            render(payload);
            syncUrl(payload);
        } catch (error) {
            showError(error.message || 'Unable to load analytics data.');
        } finally {
            if (button) button.disabled = false;
        }
    }

    const rangeSelect = document.getElementById('analytics-range');
    const customRange = document.getElementById('custom-range');
    const departmentSelect = document.getElementById('analytics-department');
    const fromInput = document.getElementById('analytics-from');
    const toInput = document.getElementById('analytics-to');
    const filterForm = document.getElementById('analytics-filters');
    const refreshButton = document.getElementById('analytics-refresh');

    if (rangeSelect) {
        rangeSelect.addEventListener('change', function () {
            const isCustom = rangeSelect.value === 'custom';
            if (customRange) {
                customRange.classList.toggle('hidden', !isCustom);
                customRange.classList.toggle('flex', isCustom);
            }
            if (!isCustom) loadAnalytics();
        });
    }

    if (departmentSelect) {
        departmentSelect.addEventListener('change', loadAnalytics);
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loadAnalytics();
        });
    }

    [fromInput, toInput].forEach(function (input) {
        if (input) input.addEventListener('change', function () {
            if (rangeSelect && rangeSelect.value === 'custom') loadAnalytics();
        });
    });

    if (refreshButton) refreshButton.addEventListener('click', loadAnalytics);

    if (typeof Chart === 'undefined') {
        showError('Chart.js failed to load. Check your internet connection and refresh the page.');
    } else {
        loadAnalytics();
    }

    setInterval(function () {
        if (document.hidden) return;
        if (rangeSelect && rangeSelect.value === 'today') loadAnalytics();
    }, 60000);
})();
</script>
@endpush
