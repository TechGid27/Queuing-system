<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\Purpose;
use App\Models\QueueAction;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class WalkInQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_walk_in_ticket_without_phone(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Juan Dela Cruz',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('queue_entries', [
            'department_id' => $department->id,
            'name' => 'Juan Dela Cruz',
            'ticket_number' => '001',
            'status' => 'waiting',
            'phone_number' => null,
            'guest_id' => null,
            'source' => 'walk_in',
            'purpose' => 'Payments',
        ]);

        $this->assertDatabaseHas('queue_actions', [
            'department_id' => $department->id,
            'action' => 'walk_in_created',
            'actor_id' => $staff->id,
            'to_status' => 'waiting',
            'ticket_number' => '001',
        ]);
    }

    public function test_walk_in_reuses_daily_ticket_sequence(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        QueueEntry::create([
            'ticket_number' => '005',
            'name' => 'Online Student',
            'purpose' => 'Payments',
            'phone_number' => '09170000111',
            'status' => 'completed',
            'department_id' => $department->id,
            'queue_date' => today(),
            'source' => 'online',
        ]);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Maria Santos',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'name' => 'Maria Santos',
            'ticket_number' => '006',
            'source' => 'walk_in',
        ]);
    }

    public function test_admin_cannot_create_walk_in_tickets(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $admin = $this->createAdmin();
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Blocked Person',
                'purpose_id' => $purpose->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('queue_entries', ['name' => 'Blocked Person']);
    }

    public function test_walk_in_rejected_when_department_paused(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['queue_paused' => true]);
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Paused Queue Person',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('queue_entries', ['name' => 'Paused Queue Person']);
    }

    public function test_walk_in_rejected_when_duplicate_active_name(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        QueueEntry::create([
            'ticket_number' => '001',
            'name' => 'Repeat Visitor',
            'purpose' => 'Payments',
            'phone_number' => null,
            'status' => 'waiting',
            'department_id' => $department->id,
            'queue_date' => today(),
            'source' => 'walk_in',
        ]);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Repeat Visitor',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, QueueEntry::where('name', 'Repeat Visitor')
            ->where('department_id', $department->id)
            ->where('status', 'waiting')
            ->count());
    }

    public function test_walk_in_requires_name_and_purpose(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
            ])
            ->assertSessionHasErrors(['name', 'purpose_id']);
    }

    public function test_walk_in_can_be_called_next_and_has_no_sms(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Senior Citizen',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasNoErrors();

        $entry = QueueEntry::where('name', 'Senior Citizen')->firstOrFail();

        // No SMS for walk-ins — mock must never receive phone calls with null.
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendNowServingNotification')->never();
            $mock->shouldReceive('sendAlmostYourTurnNotification')->never();
        });

        $this->actingAs($staff, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $entry->id,
            'status' => 'serving',
            'phone_number' => null,
        ]);
    }

    public function test_walk_in_other_purpose_stores_free_text(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Other Concern',
                'purpose_id' => 'other',
                'other_purpose' => 'Ask about ID renewal',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'name' => 'Other Concern',
            'purpose' => 'Ask about ID renewal',
            'purpose_id' => null,
            'source' => 'walk_in',
        ]);
    }

    public function test_walk_in_rejected_when_capacity_full(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $purpose = Purpose::create(['name' => 'Payments', 'is_active' => true]);

        config(['queue_system.max_capacity' => 2]);
        foreach (['A', 'B'] as $i => $name) {
            QueueEntry::create([
                'ticket_number' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'purpose' => 'Payments',
                'phone_number' => '09170000222',
                'status' => 'waiting',
                'department_id' => $department->id,
                'queue_date' => today(),
            ]);
        }

        $this->actingAs($staff, 'web')
            ->post(route('admin.walkIn'), [
                'department_id' => $department->id,
                'name' => 'Overflow Person',
                'purpose_id' => $purpose->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('queue_entries', ['name' => 'Overflow Person']);
    }

    private function createStaff(Department $department): User
    {
        return User::create([
            'department_id' => $department->id,
            'name' => 'Walk-in Staff',
            'email' => 'walkin-staff@example.com',
            'phone_number' => '09170000061',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    private function createAdmin(): User
    {
        return User::create([
            'department_id' => null,
            'name' => 'Admin',
            'email' => 'walkin-admin@example.com',
            'phone_number' => '09170000062',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
