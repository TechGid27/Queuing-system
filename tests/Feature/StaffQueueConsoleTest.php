<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\Purpose;
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
                ->with($waiting->phone_number, $waiting->ticket_number, $department->name, 'Window 1');
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

    public function test_serving_window_card_shows_auto_skip_countdown(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $entry = $this->createEntry($department, '003', 'serving');
        $entry->update([
            'counter_id' => $counter->id,
            'served_at' => now()->subMinute(),
        ]);

        $html = $this->actingAs($staff, 'web')
            ->get(route('admin.queue'))
            ->assertOk()
            ->assertSee('data-auto-skip', false)
            ->assertSee('Auto-skip in', false)
            ->getContent();

        $this->assertStringContainsString('data-served-at="'.$entry->fresh()->served_at->timestamp.'"', $html);
    }

    public function test_paused_window_shows_auto_skip_paused(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $counter->update(['queue_paused' => true]);
        $entry = $this->createEntry($department, '004', 'serving');
        $entry->update(['counter_id' => $counter->id]);

        $this->actingAs($staff, 'web')
            ->get(route('admin.queue'))
            ->assertOk()
            ->assertSee('data-auto-skip', false)
            ->assertSee('data-paused="1"', false)
            ->assertSee('Auto-skip paused', false);
    }

    public function test_waiting_list_payload_includes_served_at_for_countdown(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $counter = $department->counters()->where('name', 'Window 1')->firstOrFail();
        $entry = $this->createEntry($department, '005', 'serving');
        $entry->update([
            'counter_id' => $counter->id,
            'served_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($staff, 'web')
            ->getJson(route('admin.waitingList', ['department_id' => $department->id]))
            ->assertOk()
            ->assertJsonStructure(['currents' => [['id', 'ticket_number', 'served_at_ts']]]);

        $this->assertSame($entry->fresh()->served_at->timestamp, $response->json('currents.0.served_at_ts'));
    }

    public function test_call_next_is_disabled_when_no_waiting_ticket_matches_the_window(): void
    {
        $department = Department::create(['name' => 'Admission']);
        $window = $department->counters()->create(['name' => 'Window 1']);
        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $payment = Purpose::create(['name' => 'Payment', 'is_active' => true]);
        $window->purposes()->attach($clearance->id);
        $department->counters()->create(['name' => 'Window 2'])->purposes()->attach($payment->id);

        // Only a ticket for another window's purpose waits: Window 1 cannot call it.
        $this->createPurposeEntry($department, '001', 'Payment', $payment->id);

        $staff = $this->createStaff($department);

        $this->actingAs($staff, 'web')
            ->get(route('admin.queue', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('data-empty="1"', false)
            ->assertSee('No waiting tickets match this window', false);

        // A matching ticket enables the button again.
        $this->createPurposeEntry($department, '002', 'Clearance', $clearance->id);

        $this->actingAs($staff, 'web')
            ->get(route('admin.queue', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('data-empty="0"', false);
    }

    public function test_waiting_list_reports_eligible_counts_per_window(): void
    {
        $department = Department::create(['name' => 'Admission']);
        $window = $department->counters()->create(['name' => 'Window 1']);
        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $payment = Purpose::create(['name' => 'Payment', 'is_active' => true]);
        $window->purposes()->attach($clearance->id);
        $department->counters()->create(['name' => 'Window 2'])->purposes()->attach($payment->id);
        $staff = $this->createStaff($department);

        $this->createPurposeEntry($department, '001', 'Payment', $payment->id);

        $this->actingAs($staff, 'web')
            ->getJson(route('admin.waitingList', ['department_id' => $department->id]))
            ->assertOk()
            ->assertJsonPath('eligible_counts.'.$window->id, 0);

        // Matching + free-text ("Other"): both windows hold one purpose, so each
        // takes its own tickets plus the free-text one.
        $this->createPurposeEntry($department, '002', 'Clearance', $clearance->id);
        $this->createPurposeEntry($department, '003', 'Scholarship form', null);

        $this->actingAs($staff, 'web')
            ->getJson(route('admin.waitingList', ['department_id' => $department->id]))
            ->assertOk()
            ->assertJsonPath('eligible_counts.'.$window->id, 2)
            ->assertJsonPath('waiting_count', 3);
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

    private function createPurposeEntry(Department $department, string $ticket, string $purpose, ?int $purposeId): QueueEntry
    {
        return QueueEntry::create([
            'ticket_number' => $ticket,
            'name' => 'Queue Guest',
            'purpose' => $purpose,
            'purpose_id' => $purposeId,
            'phone_number' => '09170000999',
            'status' => 'waiting',
            'department_id' => $department->id,
            'queue_date' => today(),
        ]);
    }
}
