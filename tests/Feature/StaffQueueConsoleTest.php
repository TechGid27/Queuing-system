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
            ->assertSee('Window 1')
            ->assertSee('My window')
            ->assertSee('Focus window')
            ->assertSee('001')
            ->assertSee('Skip')
            ->assertSee('Complete');
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
        $this->mock(SmsService::class, function (MockInterface $mock) use ($waiting, $department) {
            $mock->shouldReceive('sendNowServingNotification')
                ->once()
                ->with($waiting->phone_number, $waiting->ticket_number, $department->name);
        });

        $this->actingAs($staff, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $waiting->id,
            'status' => 'serving',
        ]);
    }

    public function test_multiple_counters_serve_concurrently(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::create(['name' => 'Admission']);
        foreach (['Window 1', 'Window 2', 'Window 3', 'Window 4'] as $name) {
            $department->counters()->create(['name' => $name]);
        }
        $staffA = User::create([
            'department_id' => $department->id,
            'name' => 'Staff A',
            'email' => 'staff-a@example.com',
            'phone_number' => '09170000101',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
        $staffB = User::create([
            'department_id' => $department->id,
            'name' => 'Staff B',
            'email' => 'staff-b@example.com',
            'phone_number' => '09170000102',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
        foreach (['001', '002', '003', '004', '005'] as $ticket) {
            $this->createEntry($department, $ticket, 'waiting');
        }
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendNowServingNotification')->twice();
            $mock->shouldReceive('sendAlmostYourTurnNotification')->twice();
        });

        $this->actingAs($staffA, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($staffB, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();

        $servings = QueueEntry::where('department_id', $department->id)
            ->where('status', 'serving')
            ->get();

        $this->assertCount(2, $servings);
        $this->assertNotEquals($servings[0]->counter_id, $servings[1]->counter_id);
        $this->assertNotEquals($servings[0]->served_by, $servings[1]->served_by);

        // Same staff cannot occupy a second counter while serving.
        $this->actingAs($staffA, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHas('warning');
    }

    public function test_staff_can_pause_and_resume_a_specific_window(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['auto_pause_enabled' => false]);
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $counter->update(['auto_pause_enabled' => false]);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.togglePause'), [
                'department_id' => $department->id,
                'counter_id' => $counter->id,
                'action' => 'pause',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'counter_id' => $counter->id,
                'queue_paused' => true,
                'pause_mode' => 'manual',
            ]);

        $this->assertTrue($counter->fresh()->queue_paused);
        $this->assertFalse($department->fresh()->queue_paused);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.togglePause'), [
                'department_id' => $department->id,
                'counter_id' => $counter->id,
                'action' => 'resume',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'queue_paused' => false]);

        $this->assertFalse($counter->fresh()->queue_paused);
    }

    public function test_window_on_automatic_mode_cannot_be_paused_manually(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $counter->update(['auto_pause_enabled' => true]);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.togglePause'), [
                'department_id' => $department->id,
                'counter_id' => $counter->id,
                'action' => 'pause',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertFalse($counter->fresh()->queue_paused);
    }

    public function test_switching_window_to_manual_mode_clears_lunch_auto_pause(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $counter->update([
            'auto_pause_enabled' => true,
            'lunch_break_paused' => true,
            'queue_paused' => true,
        ]);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.pauseMode'), [
                'department_id' => $department->id,
                'counter_id' => $counter->id,
                'mode' => 'manual',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'pause_mode' => 'manual',
                'auto_pause_enabled' => false,
            ]);

        $counter->refresh();
        $this->assertFalse($counter->auto_pause_enabled);
        $this->assertFalse($counter->lunch_break_paused);
        $this->assertTrue($counter->queue_paused);
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
