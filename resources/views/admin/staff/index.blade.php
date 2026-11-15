@extends('layouts.app')
@section('page-title', 'Staff')

@section('content')
<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-black text-slate-900">Staff</h1>
            <p class="text-sm text-slate-400 mt-1">Manage staff accounts. Each staff account belongs to one department.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
            {{ $errors->first() }}
        </div>
    @endif

    <details class="bg-white rounded-2xl border border-slate-200" @if($errors->any()) open @endif>
        <summary class="px-5 py-4 cursor-pointer select-none text-sm font-bold text-slate-800 flex items-center gap-2">
            <i class="bi bi-person-plus-fill"></i> Add Staff Account
        </summary>
        <form action="{{ route('admin.staff.store') }}" method="POST" class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
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
    </details>

    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <form method="GET" action="{{ route('admin.staff.index') }}" class="px-5 py-4 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-4 gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, email, or phone..."
                class="sm:col-span-2 w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <select name="department_id" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-sm" onchange="this.form.submit()">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="status" class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-sm" onchange="this.form.submit()">
                    <option value="">Any status</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold">Filter</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-slate-500 uppercase tracking-widest bg-slate-50">
                        <th class="px-5 py-2.5">Name</th>
                        <th class="px-3 py-2.5">Contact</th>
                        <th class="px-3 py-2.5">Department</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-5 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($staff as $member)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-slate-800">{{ $member->name }}</td>
                            <td class="px-3 py-3 text-xs text-slate-500">
                                <div>{{ $member->email }}</div>
                                <div>{{ $member->phone_number }}</div>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $member->department?->name ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $member->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form action="{{ route('admin.staff.status', $member) }}" method="POST" class="inline"
                                    onsubmit="return confirm('{{ $member->is_active ? 'Deactivate this staff account?' : 'Activate this staff account?' }}')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">
                                    <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg border {{ $member->is_active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                                        {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No staff found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($staff->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">{{ $staff->links() }}</div>
        @endif
    </section>
</div>
@endsection
