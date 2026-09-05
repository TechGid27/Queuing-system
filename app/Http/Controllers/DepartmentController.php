<?php

namespace App\Http\Controllers;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['staff' => fn ($query) => $query->orderBy('name')])
            ->withCount('queueEntries')
            ->orderBy('name')
            ->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_name' => 'required|string|max:255|unique:departments,name',
        ]);

        Department::create(['name' => $validated['department_name']]);

        return back()->with('success', 'Department added successfully.');
    }

    public function updateStatus(Request $request, Department $department)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $isActive = (bool) $validated['is_active'];
        $department->update([
            'is_active' => $isActive,
            'queue_paused' => $isActive ? $department->queue_paused : true,
            'lunch_break_paused' => false,
        ]);

        $todayQueue = QueueEntry::where('department_id', $department->id)
            ->whereDate('queue_date', today());
        $serving = (clone $todayQueue)->where('status', 'serving')->first();
        $next = (clone $todayQueue)->where('status', 'waiting')->orderBy('id')->first();

        event(new QueueUpdated(
            $department->id,
            $serving?->ticket_number ?? 'Waiting',
            $next?->ticket_number ?? 'Waiting',
            (clone $todayQueue)->where('status', 'waiting')->count()
        ));

        return back()->with('success', $department->is_active
            ? 'Department activated successfully.'
            : 'Department deactivated. New tickets and staff access are now disabled.');
    }

    public function storeStaff(Request $request)
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

        return back()->with('success', "Staff account added to {$department->name}.");
    }

    public function updateStaffStatus(Request $request, User $staff)
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
