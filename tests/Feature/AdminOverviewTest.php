<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_summarizes_all_department_queues(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com', null);
        $cashier = Department::where('name', 'Cashier')->firstOrFail();
        $registrar = Department::create(['name' => 'Registrar']);
        $this->createEntry($cashier, '001', 'serving');
        $this->createEntry($cashier, '002', 'waiting');
        $this->createEntry($registrar, '003', 'completed', now()->subMinutes(8), now());

        $response = $this->actingAs($admin, 'web')->get(route('admin.overview'));

        $response->assertOk()
            ->assertSee('Admin Overview')
            ->assertSee('Cashier')
            ->assertSee('Registrar')
            ->assertSee('001')
            ->assertSee('Waiting now')
            ->assertSee('System health');
    }

    public function test_admin_overview_status_returns_department_metrics(): void
    {
        $admin = $this->createUser('admin', 'admin-status@example.com', null);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['queue_paused' => true]);
        $this->createEntry($department, '004', 'waiting');

        $response = $this->actingAs($admin, 'web')
            ->getJson(route('admin.overview.status'));

        $response->assertOk()
            ->assertJsonPath('today.waiting', 1)
            ->assertJsonPath('departments.0.name', 'Cashier')
            ->assertJsonPath('departments.0.queue_paused', true)
            ->assertJsonPath('departments.0.waiting_count', 1);
    }

    public function test_staff_cannot_access_admin_overview(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', 'staff-overview@example.com', $department);

        $this->actingAs($staff, 'web')
            ->get(route('admin.overview'))
            ->assertRedirect(route('admin.index'));
    }

    private function createUser(string $role, string $email, ?Department $department): User
    {
        return User::create([
            'department_id' => $department?->id,
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone_number' => $role === 'admin' ? '09170000031' : '09170000032',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createEntry(
        Department $department,
        string $ticket,
        string $status,
        $servedAt = null,
        $completedAt = null
    ): QueueEntry {
        return QueueEntry::create([
            'ticket_number' => $ticket,
            'name' => 'Test Guest',
            'purpose' => 'Inquiry',
            'phone_number' => '09170000999',
            'status' => $status,
            'served_at' => $servedAt,
            'completed_at' => $completedAt,
            'department_id' => $department->id,
            'queue_date' => today(),
        ]);
    }
}
