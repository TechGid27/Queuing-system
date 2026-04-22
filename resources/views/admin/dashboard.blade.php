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
            <div class="text-2xl font-black text-yellow-600 leading-tight">{{ $waitingCount }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
            <i class="bi bi-person-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Serving</div>
            <div class="text-2xl font-black text-blue-600 leading-tight">{{ $currentServing ? 1 : 0 }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-600 shrink-0">
            <i class="bi bi-check-circle-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Completed</div>
            <div class="text-2xl font-black text-green-600 leading-tight">{{ $completedCount }}</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-600 shrink-0">
            <i class="bi bi-x-circle-fill text-lg"></i>
        </div>
        <div>
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">No Response</div>
            <div class="text-2xl font-black text-red-600 leading-tight">{{ $skippedCount }}</div>
        </div>
    </div>
</div>

{{-- Main panels --}}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Now Serving --}}
    <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 p-5 lg:p-7">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Now Serving</h2>
            <span class="badge-live inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full">
                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> LIVE
            </span>
        </div>

        @if($currentServing)
            <div class="text-center py-4">
                <div class="ticket-xl text-primary mb-3">{{ $currentServing->ticket_number }}</div>
                <div class="text-lg font-bold text-slate-800">{{ $currentServing->name }}</div>
                <div class="text-sm text-slate-400 mt-1">{{ $currentServing->purpose }}</div>
                <div class="mt-2">
                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                        <i class="bi bi-phone"></i> {{ $currentServing->phone_number }}
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
    </div>

    {{-- Waiting List --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">Waiting List</h2>
            <span class="bg-yellow-50 text-yellow-700 text-xs font-bold px-2.5 py-1 rounded-full">{{ $waitingCount }} in queue</span>
        </div>
        <div class="overflow-y-auto flex-1">
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

@section('scripts')
<script>
    setInterval(() => {
        const el = document.getElementById('topbar-clock');
        if (el) el.innerText = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }, 1000);
</script>
@endsection
