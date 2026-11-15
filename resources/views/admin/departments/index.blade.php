@extends('layouts.app')
@section('page-title', 'Departments & Staff')

@section('content')
<div class="flex flex-col gap-5">
    <div>
        <h1 class="text-xl font-black text-slate-900">Departments & Staff</h1>
        <p class="text-sm text-slate-400 mt-1">Create department queues and assign each staff account to one department.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
        <section class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-4">Add Department</h2>
            <form action="{{ route('admin.departments.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department Name</label>
                    <input type="text" name="department_name" value="{{ old('department_name') }}" required maxlength="255"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        placeholder="e.g. Cashier">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Front Desks / Counters</label>
                    <input type="number" name="counters" value="{{ old('counters', 1) }}" required min="1" max="20"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <p class="text-[11px] text-slate-400 mt-1">e.g. Admission = 4, Cashier = 2. Each counter serves one ticket at a time.</p>
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                    <i class="bi bi-building-add"></i> Add Department
                </button>
            </form>
        </section>

        <section class="xl:col-span-3 bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-4">Add Staff Account</h2>
            <form action="{{ route('admin.staff.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department</label>
                    <select name="department_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        <option value="">Choose department...</option>
                        @foreach($departments->where('is_active', true) as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Phone Number</label>
                    <input type="text" name="phone_number" value="{{ old('phone_number') }}" required maxlength="11" inputmode="numeric" placeholder="09xxxxxxxxx"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Temporary Password</label>
                    <input type="password" name="password" required minlength="8" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Confirm Password</label>
                    <input type="password" name="password_confirmation" required minlength="8" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </div>
                <button type="submit" class="sm:col-span-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                    <i class="bi bi-person-plus-fill"></i> Add Staff
                </button>
            </form>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        @forelse($departments as $department)
            <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <header class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-slate-900">{{ $department->name }}</h2>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $department->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $department->is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">{{ $department->staff->count() }} staff · {{ $department->counters->count() }} counters · {{ $department->queue_entries_count }} total tickets</p>
                    </div>
                    <form action="{{ route('admin.departments.status', $department) }}" method="POST"
                        onsubmit="return confirm('{{ $department->is_active ? 'Deactivate this department and block its staff queue access?' : 'Activate this department?' }}')">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $department->is_active ? 0 : 1 }}">
                        <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border {{ $department->is_active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                            {{ $department->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                </header>

                <div class="px-5 py-3 border-t border-slate-100">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Counters ({{ $department->counters->count() }})</div>
                    <div class="flex flex-col gap-2 mb-3">
                        @forelse($department->counters as $counter)
                            <div class="rounded-xl border border-slate-200 px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-slate-700">{{ $counter->name }}</span>
                                        @if($counter->purposes->isEmpty())
                                            <span class="ml-1.5 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-400">All purposes</span>
                                        @else
                                            <span class="ml-1.5 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">{{ $counter->purposes->count() }} purpose{{ $counter->purposes->count() > 1 ? 's' : '' }}</span>
                                        @endif
                                    </div>
                                    <form action="{{ route('admin.counters.status', $counter) }}" method="POST" class="inline-flex items-center gap-1.5 text-[11px] font-semibold">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $counter->is_active ? 0 : 1 }}">
                                        <span class="px-2 py-0.5 rounded-full border {{ $counter->is_active ? 'border-green-200 bg-green-50 text-green-700' : 'border-slate-200 bg-slate-50 text-slate-400' }}">{{ $counter->is_active ? 'Active' : 'Off' }}</span>
                                        <button type="submit" class="underline text-slate-500 hover:text-slate-700">{{ $counter->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                </div>
                                @if($counter->purposes->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @foreach($counter->purposes as $purpose)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">{{ $purpose->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <details class="mt-1.5">
                                    <summary class="text-[11px] font-semibold text-slate-400 hover:text-slate-600 cursor-pointer select-none">Assign purposes</summary>
                                    <form action="{{ route('admin.counters.purposes', $counter) }}" method="POST" class="mt-2" data-purpose-form="{{ $department->id }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            @forelse($purposes as $purpose)
                                                @php
                                                    $takenByOther = $department->counters
                                                        ->where('id', '!=', $counter->id)
                                                        ->contains(fn ($other) => $other->purposes->contains('id', $purpose->id));
                                                @endphp
                                                <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 cursor-pointer hover:bg-slate-100 {{ $takenByOther ? 'opacity-50 cursor-not-allowed' : '' }}" data-purpose-label>
                                                    <input type="checkbox" name="purpose_ids[]" value="{{ $purpose->id }}"
                                                        class="rounded border-slate-300 text-primary focus:ring-primary/30"

@push('scripts')
<script>
    // A purpose can belong to one window per department: checking it in one
    // window immediately disables it in the other windows of that department.
    (function () {
        function sync(deptId) {
            var forms = document.querySelectorAll('form[data-purpose-form="' + deptId + '"]');
            forms.forEach(function (form) {
                form.querySelectorAll('input[type=checkbox]').forEach(function (box) {
                    var takenElsewhere = Array.from(forms).some(function (other) {
                        if (other === form) return false;
                        return Array.from(other.querySelectorAll('input[type=checkbox]'))
                            .some(function (o) { return o.value === box.value && o.checked && !o.disabled; });
                    });
                    if (takenElsewhere) box.checked = false;
                    box.disabled = takenElsewhere;
                    var label = box.closest('[data-purpose-label]');
                    if (label) label.classList.toggle('opacity-50', takenElsewhere);
                    if (label) label.classList.toggle('cursor-not-allowed', takenElsewhere);
                });
            });
        }
        document.querySelectorAll('form[data-purpose-form]').forEach(function (form) {
            form.addEventListener('change', function () { sync(form.dataset.purposeForm); });
        });
        var seen = {};
        document.querySelectorAll('form[data-purpose-form]').forEach(function (f) {
            if (!seen[f.dataset.purposeForm]) { seen[f.dataset.purposeForm] = 1; sync(f.dataset.purposeForm); }
        });
    })();
</script>
@endpush
                                                        @checked(in_array($purpose->id, $counter->purposes->pluck('id')->all()))
                                                        @disabled($takenByOther)>
                                                    {{ $purpose->name }}
                                                </label>
                                            @empty
                                                <span class="text-[11px] text-slate-400">No purposes yet — create them under Purposes first.</span>
                                            @endforelse
                                        </div>
                                        @error('purpose_ids')<p class="text-[11px] text-red-600 mb-1">{{ $message }}</p>@enderror
                                        <button type="submit" class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-900 text-white hover:bg-slate-800">Save purposes</button>
                                        <span class="text-[10px] text-slate-400 ml-1.5">Leave all unchecked = serves everything</span>
                                    </form>
                                </details>
                            </div>
                        @empty
                            <span class="text-xs text-slate-400">No counters yet.</span>
                        @endforelse
                    </div>
                    <form action="{{ route('admin.counters.store') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ $department->id }}">
                        <input type="text" name="name" required maxlength="255" placeholder="e.g. Window {{ $department->counters->count() + 1 }}"
                            class="flex-1 px-3 py-1.5 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800">Add</button>
                    </form>
                </div>

                <div class="divide-y divide-slate-50">
                    @forelse($department->staff as $staff)
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800 truncate">{{ $staff->name }}</div>
                                <div class="text-xs text-slate-400 truncate">{{ $staff->email }} · {{ $staff->phone_number }}</div>
                            </div>
                            <form action="{{ route('admin.staff.status', $staff) }}" method="POST" class="shrink-0"
                                onsubmit="return confirm('{{ $staff->is_active ? 'Deactivate this staff account?' : 'Activate this staff account?' }}')">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $staff->is_active ? 0 : 1 }}">
                                <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg border {{ $staff->is_active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                                    {{ $staff->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">No staff assigned.</div>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                No departments created yet.
            </div>
        @endforelse
    </div>
</div>
@endsection
