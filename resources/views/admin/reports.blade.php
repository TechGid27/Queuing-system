@extends('layouts.app')
@section('page-title', 'Queue Reports')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-black text-slate-900">Queue Reports</h1>
        <p class="text-sm text-slate-400 mt-0.5">Daily activity logs and summaries</p>
    </div>
    <div class="flex flex-wrap gap-2 items-center">
        <form action="{{ route('admin.reports') }}" method="GET">
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
        </form>
        <a href="{{ route('admin.reports.download', ['date' => $date]) }}"
            class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-2 rounded-xl transition-colors">
            <i class="bi bi-download"></i> Download PDF
        </a>
    </div>
</div>

{{-- Summary Stats --}}
@php
    $total     = $entries->count();
    $completed = $entries->where('status','completed')->count();
    $skipped   = $entries->where('status','no_response')->count();
    $avgMins   = $entries->filter(fn($e) => $e->served_at && $e->completed_at)
                    ->avg(fn($e) => $e->served_at->diffInMinutes($e->completed_at));
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
            <i class="bi bi-people-fill"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Total</div>
            <div class="text-xl font-black text-blue-600">{{ $total }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-green-50 flex items-center justify-center text-green-600 shrink-0">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Completed</div>
            <div class="text-xl font-black text-green-600">{{ $completed }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center text-red-600 shrink-0">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">No Response</div>
            <div class="text-xl font-black text-red-600">{{ $skipped }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-yellow-50 flex items-center justify-center text-yellow-600 shrink-0">
            <i class="bi bi-stopwatch-fill"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Avg. Duration</div>
            <div class="text-xl font-black text-yellow-600">{{ $avgMins ? round($avgMins).'m' : '--' }}</div>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 text-sm font-bold text-slate-800">
        Records for {{ \Carbon\Carbon::parse($date)->format('F d, Y') }}
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Ticket</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Student</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden md:table-cell">Purpose</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden lg:table-cell">Phone</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Status</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden sm:table-cell">Requested</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden sm:table-cell">Served At</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden lg:table-cell">Duration</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($entries as $entry)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3.5 font-bold text-slate-800">{{ $entry->ticket_number }}</td>
                    <td class="px-5 py-3.5 text-slate-700">{{ $entry->name }}</td>
                    <td class="px-5 py-3.5 hidden md:table-cell">
                        <span class="bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1 rounded-full">{{ $entry->purpose }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden lg:table-cell">{{ $entry->phone_number }}</td>
                    <td class="px-5 py-3.5">
                        @php
                            $cls = match($entry->status) {
                                'completed'   => 'bg-green-100 text-green-700',
                                'no_response' => 'bg-red-100 text-red-700',
                                'serving'     => 'bg-blue-100 text-blue-700',
                                default       => 'bg-yellow-100 text-yellow-700',
                            };
                        @endphp
                        <span class="inline-flex items-center {{ $cls }} text-xs font-semibold px-2.5 py-1 rounded-full">
                            {{ ucfirst(str_replace('_', ' ', $entry->status)) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden sm:table-cell">{{ $entry->created_at->format('h:i A') }}</td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden sm:table-cell">{{ $entry->served_at ? $entry->served_at->format('h:i A') : '--' }}</td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden lg:table-cell">
                        @if($entry->served_at && $entry->completed_at)
                            {{ $entry->served_at->diffInMinutes($entry->completed_at) }} min
                        @else --
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-14 text-slate-400">
                        <i class="bi bi-inbox text-4xl block mb-2"></i>
                        No records found for this date.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
