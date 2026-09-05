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
                        <p class="text-xs text-slate-400 mt-1">{{ $department->staff->count() }} staff · {{ $department->queue_entries_count }} total tickets</p>
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
