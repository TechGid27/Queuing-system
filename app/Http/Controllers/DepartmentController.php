<?php

namespace App\Http\Controllers;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('staff')->with([
            'counters' => fn ($query) => $query->orderBy('id')->with('purposes'),
        ])
            ->withCount('queueEntries')
            ->orderBy('name')
            ->get();

        $purposes = \App\Models\Purpose::where('is_active', true)->orderBy('name')->get();

        return view('admin.departments.index', compact('departments', 'purposes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_name' => 'required|string|max:255|unique:departments,name',
            'counters' => 'nullable|integer|min:1|max:20',
        ]);

        $department = Department::create(['name' => $validated['department_name']]);
        $count = $validated['counters'] ?? 1;
        for ($i = 1; $i <= $count; $i++) {
            $department->counters()->create(['name' => "Window {$i}"]);
        }

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
            ->where('queue_date', today()->toDateString());
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

    public function storeCounter(Request $request)
    {

    public function updateCounterPurposes(Request $request, \App\Models\Counter $counter)
    {
        $validated = $request->validate([
            'purpose_ids' => 'nullable|array',
            'purpose_ids.*' => 'integer|exists:purposes,id',
        ]);

        $purposeIds = array_values(array_unique($validated['purpose_ids'] ?? []));

        // A purpose can only belong to one window per department.
        $taken = \Illuminate\Support\Facades\DB::table('counter_purpose')
            ->join('counters', 'counters.id', '=', 'counter_purpose.counter_id')
            ->join('purposes', 'purposes.id', '=', 'counter_purpose.purpose_id')
            ->where('counters.department_id', $counter->department_id)
            ->where('counters.id', '!=', $counter->id)
            ->whereIn('counter_purpose.purpose_id', $purposeIds)
            ->pluck('purposes.name');

        if ($taken->isNotEmpty()) {
            return back()->withErrors([
                'purpose_ids' => 'Already assigned to another window: '.$taken->unique()->implode(', ').'.',
            ]);
        }

        $counter->purposes()->sync($purposeIds);

        $assigned = $counter->purposes()->pluck('name');
        $summary = $assigned->isEmpty()
            ? "{$counter->name} now serves all purposes."
            : "{$counter->name} now handles: {$assigned->implode(', ')}.";

        return back()->with('success', $summary);
    }
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('counters', 'name')->where(
                    fn ($query) => $query->where('department_id', $request->integer('department_id'))
                ),
            ],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        $department->counters()->create(['name' => $validated['name']]);

        return back()->with('success', "Counter added to {$department->name}.");
    }

    public function updateCounterStatus(Request $request, \App\Models\Counter $counter)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $isActive = (bool) $validated['is_active'];

        if (! $isActive) {
            $busy = QueueEntry::where('counter_id', $counter->id)
                ->where('queue_date', today()->toDateString())
                ->where('status', 'serving')
                ->exists();

            if ($busy) {
                return back()->with('warning', "{$counter->name} is currently serving and cannot be deactivated.");
            }
        }

        $counter->update(['is_active' => $isActive]);

        return back()->with('success', $isActive
            ? "{$counter->name} activated."
            : "{$counter->name} deactivated.");
    }
}
