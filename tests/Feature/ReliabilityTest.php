<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueAction;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_transitions_are_recorded_with_the_actor(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createStaff($department);
        $waiting = $this->createEntry($department, '001', 'waiting');
        $this->mock(SmsService::class, function (MockInterface $mock) use ($waiting) {
            $mock->shouldReceive('sendNowServingNotification')->once();
            $mock->shouldReceive('sendCompletedNotification')->once();
        });

        $this->actingAs($staff, 'web')
            ->post(route('admin.callNext'), ['department_id' => $department->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($staff, 'web')
            ->post(route('admin.complete', $waiting))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_actions', [
            'action' => 'called',
            'queue_entry_id' => $waiting->id,
            'actor_id' => $staff->id,
            'from_status' => 'waiting',
            'to_status' => 'serving',
        ]);
        $this->assertDatabaseHas('queue_actions', [
            'action' => 'completed',
            'queue_entry_id' => $waiting->id,
            'actor_id' => $staff->id,
            'from_status' => 'serving',
            'to_status' => 'completed',
        ]);
    }

    public function test_auto_skip_uses_the_same_transition_log_and_locking_path(): void
    {
        Event::fake([QueueUpdated::class]);
        Carbon::setTestNow(now());
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $serving = $this->createEntry($department, '001', 'serving', now()->subMinutes(4));
        $waiting = $this->createEntry($department, '002', 'waiting');
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendSkippedNotification')->once();
            $mock->shouldReceive('sendNowServingNotification')->once();
            $mock->shouldReceive('sendAlmostYourTurnNotification')->never();
        });

        $this->artisan('queue:auto-skip')->assertExitCode(0);

        $this->assertDatabaseHas('queue_entries', ['id' => $serving->id, 'status' => 'no_response']);
        $this->assertDatabaseHas('queue_entries', ['id' => $waiting->id, 'status' => 'serving']);
        $this->assertDatabaseHas('queue_actions', [
            'action' => 'auto_skipped',
            'queue_entry_id' => $serving->id,
        ]);
        $this->assertDatabaseHas('queue_actions', [
            'action' => 'auto_called',
            'queue_entry_id' => $waiting->id,
        ]);
    }

    public function test_pause_and_resume_are_audited(): void
    {
        Event::fake([QueueUpdated::class]);
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['auto_pause_enabled' => false]);
        $staff = $this->createStaff($department);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.togglePause'), [
                'department_id' => $department->id,
                'action' => 'pause',
            ])
            ->assertOk()
            ->assertJsonPath('queue_paused', true);

        $this->actingAs($staff, 'web')
            ->postJson(route('admin.togglePause'), [
                'department_id' => $department->id,
                'action' => 'resume',
            ])
            ->assertOk()
            ->assertJsonPath('queue_paused', false);

        $this->assertDatabaseHas('queue_actions', [
            'action' => 'paused',
            'department_id' => $department->id,
            'actor_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('queue_actions', [
            'action' => 'resumed',
            'department_id' => $department->id,
            'actor_id' => $staff->id,
        ]);
    }

    public function test_sms_fallback_records_delivery_status_without_storing_message_content(): void
    {
        config([
            'services.textbee.key' => null,
            'services.textbee.device_id' => null,
        ]);

        app(SmsService::class)->sendOtp('09171234567', '123456');

        $this->assertDatabaseHas('sms_notifications', [
            'phone_number' => '+639171234567',
            'type' => 'otp',
            'status' => 'fallback',
        ]);
        $this->assertDatabaseCount('sms_notifications', 1);
    }

    public function test_admin_can_view_the_audit_log(): void
    {
        $admin = $this->createUser('admin', null, 'audit-admin@example.com', '09170000061');
        $department = Department::where('name', 'Cashier')->firstOrFail();
        QueueAction::create([
            'department_id' => $department->id,
            'action' => 'paused',
            'metadata' => ['source' => 'manual'],
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Queue Audit Log')
            ->assertSee('Paused');
    }

    public function test_admin_cannot_control_a_ticket_from_an_inactive_department(): void
    {
        $admin = $this->createUser('admin', null, 'inactive-admin@example.com', '09170000062');
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['is_active' => false, 'queue_paused' => true]);
        $ticket = $this->createEntry($department, '001', 'serving');

        $this->actingAs($admin, 'web')
            ->post(route('admin.complete', $ticket))
            ->assertForbidden();

        $this->assertDatabaseHas('queue_entries', ['id' => $ticket->id, 'status' => 'serving']);
    }

    private function createStaff(Department $department): User
    {
        return $this->createUser('staff', $department, 'staff-'.uniqid().'@example.com', '09170000063');
    }

    private function createUser(string $role, ?Department $department, string $email, string $phone): User
    {
        return User::create([
            'department_id' => $department?->id,
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone_number' => $phone,
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createEntry(Department $department, string $ticket, string $status, $servedAt = null): QueueEntry
    {
        return QueueEntry::create([
            'ticket_number' => $ticket,
            'name' => 'Reliability Guest',
            'purpose' => 'Inquiry',
            'phone_number' => '09170000997',
            'status' => $status,
            'served_at' => $servedAt,
            'department_id' => $department->id,
            'queue_date' => today(),
        ]);
    }
}
