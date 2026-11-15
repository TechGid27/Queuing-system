<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $departmentId = $request->integer('department_id') ?: null;
        $status = $request->query('status');

        $staff = User::where('role', 'staff')
            ->with('department')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone_number', 'like', $like);
                });
            })
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();

        return view('admin.staff.index', [
            'staff' => $staff,
            'departments' => $departments,
            'search' => $search,
            'departmentId' => $departmentId,
            'status' => $status,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone_number' => 'required|regex:/^09[0-9]{9}$/|unique:users,phone_number',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $department = Department::active()->findOrFail($validated['department_id']);

        User::create([
            'department_id' => $department->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'phone_verified_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
            'is_active' => true,
        ]);

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff account added to {$department->name}.");
    }

    public function updateStatus(Request $request, User $staff)
    {
        abort_unless($staff->role === 'staff', 404);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $staff->update(['is_active' => $validated['is_active']]);

        return back()->with('success', $staff->is_active
            ? 'Staff account activated successfully.'
            : 'Staff account deactivated successfully.');
    }
}
