@extends('layouts.app')
@section('page-title', 'Manage Purposes')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-black text-slate-900">Manage Purposes</h1>
        <p class="text-sm text-slate-400 mt-0.5">Control which purposes appear in the student queue form</p>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-sm px-4 py-2.5 rounded-xl transition-colors">
        <i class="bi bi-plus-lg"></i> Add Purpose
    </button>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Purpose Name</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Status</th>
                    <th class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3 hidden sm:table-cell">Added</th>
                    <th class="text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wide px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($purposes as $purpose)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3.5 font-semibold text-slate-800">{{ $purpose->name }}</td>
                    <td class="px-5 py-3.5">
                        @if($purpose->is_active)
                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="bi bi-check-circle-fill"></i> Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="bi bi-x-circle"></i> Inactive
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden sm:table-cell">{{ $purpose->created_at->format('M d, Y') }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-2">
                            <form action="{{ route('admin.purposes.update', $purpose->id) }}" method="POST">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $purpose->is_active ? '0' : '1' }}">
                                <button type="submit"
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors
                                    {{ $purpose->is_active
                                        ? 'border-slate-200 text-slate-500 hover:bg-slate-50'
                                        : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                                    {{ $purpose->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form action="{{ route('admin.purposes.destroy', $purpose->id) }}" method="POST"
                                onsubmit="return confirm('Delete \'{{ addslashes($purpose->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-14 text-slate-400">
                        <i class="bi bi-tags text-4xl block mb-2"></i>
                        No purposes yet. Click "Add Purpose" to begin.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-slate-900">Add New Purpose</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 transition-colors text-lg">
                ×
            </button>
        </div>
        <form action="{{ route('admin.purposes.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Purpose Name</label>
                <input type="text" name="name" required autofocus
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
                    placeholder="e.g. Enrollment">
                <p class="text-xs text-slate-400 mt-1">This will appear in the student's "Purpose of Visit" dropdown.</p>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                    class="inline-flex items-center gap-1 border border-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-1 bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                    Save Purpose
                </button>
            </div>
        </form>
    </div>
</div>

@if($errors->has('name'))
<script>document.getElementById('addModal').classList.remove('hidden');</script>
@endif

@endsection
