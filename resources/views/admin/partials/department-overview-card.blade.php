@php
    $state = !$department['is_active']
        ? ['label' => 'INACTIVE', 'classes' => 'bg-slate-100 text-slate-500', 'dot' => 'bg-slate-400']
        : ($department['queue_paused']
            ? ['label' => 'PAUSED', 'classes' => 'bg-yellow-50 text-yellow-700', 'dot' => 'bg-yellow-500']
            : ['label' => 'LIVE', 'classes' => 'bg-green-50 text-green-700', 'dot' => 'bg-green-500']);
@endphp
<article class="rounded-xl border border-slate-200 p-4 hover:border-primary/40 transition-colors">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-900">{{ $department['name'] }}</h3>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ $department['active_staff_count'] }}/{{ $department['staff_count'] }} active staff</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold rounded-full px-2 py-1 {{ $state['classes'] }}"><span class="w-1.5 h-1.5 rounded-full {{ $state['dot'] }}"></span>{{ $state['label'] }}</span>
    </div>
    <div class="grid grid-cols-3 gap-2 mt-4">
        <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Serving</div><div class="text-base font-black text-primary mt-0.5">{{ $department['current_ticket'] ?? 'Waiting' }}</div></div>
        <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Waiting</div><div class="text-base font-black text-yellow-600 mt-0.5">{{ $department['waiting_count'] }}</div></div>
        <div class="bg-slate-50 rounded-lg p-2"><div class="text-[10px] text-slate-400">Est. wait</div><div class="text-base font-black text-slate-700 mt-0.5">{{ $department['estimated_wait_minutes'] }}m</div></div>
    </div>
    <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
        <span class="text-[11px] text-slate-400">{{ $department['completed_count'] }} completed · {{ $department['no_response_count'] }} no response</span>
        <a href="{{ route('admin.queue', ['department_id' => $department['id']]) }}" class="text-[11px] font-bold text-primary hover:text-primary-dark">Open console <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
