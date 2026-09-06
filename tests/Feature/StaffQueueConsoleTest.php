<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class StaffQueueConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_the_queue_console(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $this->createEntry($department, '001', 'serving');

        $this->actingAs($staff, 'web')
            ->get(route('admin.queue'))
            ->assertOk()
            ->assertSee('Queue Console')
            ->assertSee('Skip / No Show')
            ->assertSee('Complete')
            ->assertSee('data-current="1"', false);
    }

    public function test_call_next_requires_staff_to_finish_current_ticket_first(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $current = $this->createEntry($department, '001', 'serving');
        $waiting = $this->createEntry($department, '002', 'waiting');

        $this->actingAs($staff, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHas('warning', 'Complete or skip the current ticket before calling the next student.');

        $this->assertDatabaseHas('queue_entries', ['id' => $current->id, 'status' => 'serving']);
        $this->assertDatabaseHas('queue_entries', ['id' => $waiting->id, 'status' => 'waiting']);
    }

    public function test_call_next_starts_the_oldest_waiting_ticket_when_queue_is_idle(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $waiting = $this->createEntry($department, '002', 'waiting');
        $this->mock(SmsService::class, function (MockInterface $mock) use ($waiting) {
            $mock->shouldReceive('sendNowServingNotification')
                ->once()
                ->with($waiting->phone_number, $waiting->ticket_number);
        });

        $this->actingAs($staff, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $waiting->id,
            'status' => 'serving',
        ]);
    }

    private function createStaff(Department $department): User
    {
        return User::create([
            'department_id' => $department->id,
            'name' => 'Queue Staff',
            'email' => 'queue-staff@example.com',
            'phone_number' => '09170000041',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    private function createEntry(Department $department, string $ticket, string $status): QueueEntry
    {
        return QueueEntry::create([
            'ticket_number' => $ticket,
            'name' => 'Queue Guest',
            'purpose' => 'Inquiry',
            'phone_number' => '09170000998',
            'status' => $status,
            'served_at' => $status === 'serving' ? now() : null,
            'department_id' => $department->id,
            'queue_date' => today(),
        ]);
    }
}
