@extends('layouts.app')
@section('page-title', 'Audit Log')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-6">
    <div>
        <div class="text-[11px] font-semibold text-primary uppercase tracking-[0.2em]">Reliability</div>
        <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-slate-900 mt-1">Queue Audit Log</h1>
        <p class="text-sm text-slate-500 mt-1">A record of queue transitions and pause actions.</p>
    </div>
    <a href="{{ route('admin.overview') }}" class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:text-primary-dark">
        <i class="bi bi-arrow-left"></i> Back to overview
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left min-w-[720px]">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="px-5 py-3">Time</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">Department</th>
                    <th class="px-5 py-3">Ticket</th>
                    <th class="px-5 py-3">Actor</th>
                    <th class="px-5 py-3">Transition</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($actions as $action)
                    <tr class="text-sm">
                        <td class="px-5 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $action->created_at->format('M j, g:i A') }}</td>
                        <td class="px-5 py-3"><span class="inline-flex rounded-full bg-blue-50 text-blue-700 text-[11px] font-bold px-2.5 py-1">{{ str_replace('_', ' ', ucfirst($action->action)) }}</span></td>
                        <td class="px-5 py-3 font-semibold text-slate-700">{{ $action->department?->name ?? '—' }}</td>
                        <td class="px-5 py-3 font-black text-slate-900">{{ $action->ticket_number ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $action->actor?->name ?? 'Scheduler' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $action->from_status ?? '—' }} <i class="bi bi-arrow-right mx-1"></i> {{ $action->to_status ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">No queue actions recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($actions->hasPages())
        <div class="px-5 py-3 border-t border-slate-100">{{ $actions->links() }}</div>
    @endif
</div>
@endsection
