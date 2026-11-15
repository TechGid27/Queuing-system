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

class PurposeRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $staff;

    private Purpose $payment;

    private Purpose $enrolment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['name' => 'Guidance']);
        $this->department->counters()->create(['name' => 'Window 1']);
        $this->department->counters()->create(['name' => 'Window 2']);

        $this->payment = Purpose::create(['name' => 'Payment', 'is_active' => true]);
        $this->enrolment = Purpose::create(['name' => 'Enrolment', 'is_active' => true]);

        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window1->purposes()->attach($this->payment->id);

        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $window2->purposes()->attach($this->enrolment->id);

        $this->staff = User::create([
            'department_id' => $this->department->id,
            'name' => 'Routing Staff',
            'email' => 'routing-staff@example.com',
            'phone_number' => '09170000051',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_window_only_calls_tickets_matching_its_purposes(): void
    {
        Event::fake([QueueUpdated::class]);
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendNowServingNotification')->once();
            $mock->shouldReceive('sendAlmostYourTurnNotification')->once();
        });

        // Older ticket first, but it is Enrolment — Window 1 is Payment-only.
        $enrolmentTicket = $this->createEntry('001', 'waiting', $this->enrolment);
        $paymentTicket = $this->createEntry('002', 'waiting', $this->payment);

        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window1->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $paymentTicket->id,
            'status' => 'serving',
            'counter_id' => $window1->id,
        ]);
        $this->assertDatabaseHas('queue_entries', ['id' => $enrolmentTicket->id, 'status' => 'waiting']);
    }

    public function test_window_with_no_matching_ticket_reports_warning(): void
    {
        Event::fake([QueueUpdated::class]);
        $this->createEntry('001', 'waiting', $this->enrolment);

        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window1->id,
            ])
            ->assertSessionHas('warning', "No students waiting for Window 1's services.");

        $this->assertDatabaseHas('queue_entries', ['ticket_number' => '001', 'status' => 'waiting']);
    }

    public function test_window_with_most_purposes_cannot_call_unmatched_or_free_text_tickets(): void
    {
        Event::fake([QueueUpdated::class]);

        // Inquiry is assigned to no window; "Other" has purpose_id null.
        $inquiry = Purpose::create(['name' => 'Inquiry', 'is_active' => true]);
        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $unmatched = $this->createEntry('001', 'waiting', $inquiry);
        $other = $this->createEntry('002', 'waiting', null, 'Scholarship form');

        // Window 1 has 2 purposes, Window 2 has 1 → Window 2 is the overflow window.
        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window1->purposes()->attach($clearance->id);

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window1->id,
            ])
            ->assertSessionHas('warning', "No students waiting for Window 1's services.");

        $this->assertDatabaseHas('queue_entries', ['id' => $unmatched->id, 'status' => 'waiting']);
        $this->assertDatabaseHas('queue_entries', ['id' => $other->id, 'status' => 'waiting']);
    }

    public function test_window_with_fewest_purposes_receives_other_and_unassigned_tickets(): void
    {
        Event::fake([QueueUpdated::class]);
        $this->mock(SmsService::class)->shouldIgnoreMissing();

        $inquiry = Purpose::create(['name' => 'Inquiry', 'is_active' => true]);
        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $unmatched = $this->createEntry('001', 'waiting', $inquiry);
        $other = $this->createEntry('002', 'waiting', null, 'Scholarship form');

        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $window1->purposes()->attach($clearance->id);

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $unmatched->id,
            'status' => 'serving',
            'counter_id' => $window2->id,
        ]);
    }

    public function test_general_window_can_call_unmatched_and_free_text_tickets(): void
    {
        Event::fake([QueueUpdated::class]);
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendNowServingNotification')->twice();
            // One "up next" after the first call (002 still waiting); after the
            // final call the queue is empty so no second one.
            $mock->shouldReceive('sendAlmostYourTurnNotification')->once();
            $mock->shouldReceive('sendCompletedNotification')->once();
        });

        $inquiry = Purpose::create(['name' => 'Inquiry', 'is_active' => true]);
        $unmatched = $this->createEntry('001', 'waiting', $inquiry);
        $other = $this->createEntry('002', 'waiting', null, 'Scholarship form');

        // Window 2 becomes a general window (no assigned purposes).
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $window2->purposes()->detach();

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $unmatched->id,
            'status' => 'serving',
            'counter_id' => $window2->id,
        ]);

        // Finish it so the same staff may call again; the free-text ticket remains.
        $this->actingAs($this->staff, 'web')
            ->post(route('admin.complete', $unmatched->id))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', ['id' => $other->id, 'status' => 'serving']);
        $this->assertDatabaseHas('queue_entries', ['id' => $unmatched->id, 'status' => 'completed']);
    }

    public function test_window_without_assignments_serves_everything(): void
    {
        Event::fake([QueueUpdated::class]);
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendNowServingNotification')->once();
            // Queue is empty after this call, so no "up next" SMS is sent.
            $mock->shouldReceive('sendAlmostYourTurnNotification')->never();
        });

        $this->department->counters()->where('name', 'Window 2')->firstOrFail()->purposes()->detach();

        $enrolmentTicket = $this->createEntry('001', 'waiting', $this->enrolment);
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $enrolmentTicket->id,
            'status' => 'serving',
            'counter_id' => $window2->id,
        ]);
    }

    public function test_auto_skip_refills_each_window_with_eligible_tickets(): void
    {
        // Two expired serving tickets occupy both windows; waiting queue has
        // one Enrolment (older) and one Payment ticket.
        $expiredA = $this->createEntry('010', 'serving', $this->enrolment);
        $expiredA->update(['served_at' => now()->subMinutes(4)]);
        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $expiredA->update(['counter_id' => $window1->id]);

        $expiredB = $this->createEntry('011', 'serving', $this->payment);
        $expiredB->update(['served_at' => now()->subMinutes(4), 'counter_id' => $window2->id]);

        $this->createEntry('001', 'waiting', $this->enrolment);
        $this->createEntry('002', 'waiting', $this->payment);

        $result = app(\App\Services\QueueTransitionService::class)->autoSkip($this->department->fresh());

        $this->assertNotNull($result);
        $this->assertCount(2, $result['skipped']);
        $this->assertCount(2, $result['called']);

        $paymentServing = QueueEntry::where('ticket_number', '002')->first();
        $enrolmentServing = QueueEntry::where('ticket_number', '001')->first();

        $this->assertSame('serving', $paymentServing->status);
        $this->assertSame('serving', $enrolmentServing->status);
        $this->assertSame($window1->id, $paymentServing->counter_id);
        $this->assertSame($window2->id, $enrolmentServing->counter_id);
    }

    public function test_admin_can_assign_purposes_to_a_counter(): void
    {
        $admin = User::create([
            'name' => 'Routing Admin',
            'email' => 'routing-admin@example.com',
            'phone_number' => '09170000052',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();

        // Free up the purposes so Window 1 can take them.
        $window1->purposes()->detach();
        $window2->purposes()->detach();

        $this->actingAs($admin, 'web')
            ->patch(route('admin.counters.purposes', $window1), [
                'purpose_ids' => [$this->payment->id, $this->enrolment->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(
            [$this->payment->id, $this->enrolment->id],
            $window1->purposes()->pluck('purposes.id')->all()
        );

        // A purpose already held by Window 1 cannot also go to Window 2.
        $this->actingAs($admin, 'web')
            ->patch(route('admin.counters.purposes', $window2), [
                'purpose_ids' => [$this->payment->id],
            ])
            ->assertSessionHasErrors('purpose_ids');

        $this->assertCount(0, $window2->purposes()->get());

        // Clearing all assignments restores "serves everything".
        $this->actingAs($admin, 'web')
            ->patch(route('admin.counters.purposes', $window1), ['purpose_ids' => []])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $window1->purposes()->get());
    }

    public function test_window_with_fewer_purposes_takes_other_tickets_when_its_own_are_empty(): void
    {
        $this->mock(SmsService::class)->shouldIgnoreMissing();

        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $window1->purposes()->attach($clearance->id); // Window 1: 2 purposes, Window 2: 1

        $paymentTicket = $this->createEntry('001', 'waiting', $this->payment);

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $paymentTicket->id,
            'status' => 'serving',
            'counter_id' => $window2->id,
        ]);
    }

    public function test_window_with_most_purposes_does_not_take_other_windows_tickets(): void
    {
        $clearance = Purpose::create(['name' => 'Clearance', 'is_active' => true]);
        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window1->purposes()->attach($clearance->id);

        $this->createEntry('001', 'waiting', $this->enrolment);

        $this->actingAs($this->staff, 'web')
            ->post(route('admin.callNext'), [
                'department_id' => $this->department->id,
                'counter_id' => $window1->id,
            ]);

        $this->assertDatabaseHas('queue_entries', ['ticket_number' => '001', 'status' => 'waiting']);
    }

    public function test_paused_window_purposes_fall_to_other_window_until_it_resumes(): void
    {
        $this->mock(SmsService::class)->shouldIgnoreMissing();
        Event::fake([QueueUpdated::class]);

        // Window 1 = Payment, Window 2 = Enrolment (equal counts, no helping).
        $window1 = $this->department->counters()->where('name', 'Window 1')->firstOrFail();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();

        $first = $this->createEntry('001', 'waiting', $this->payment);

        $call = fn () => $this->actingAs($this->staff, 'web')->post(route('admin.callNext'), [
            'department_id' => $this->department->id,
            'counter_id' => $window2->id,
        ]);

        // Window 1 active: Window 2 cannot take a Payment ticket.
        $call();
        $this->assertDatabaseHas('queue_entries', ['id' => $first->id, 'status' => 'waiting']);

        // Window 1 paused: Window 2 acts as a general window.
        $window1->update(['queue_paused' => true]);
        $call()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('queue_entries', [
            'id' => $first->id,
            'status' => 'serving',
            'counter_id' => $window2->id,
        ]);

        // Window 2 finishes, Window 1 resumes: Payment goes back to Window 1 only.
        QueueEntry::whereKey($first->id)->update(['status' => 'completed', 'completed_at' => now()]);
        $window1->update(['queue_paused' => false]);
        $second = $this->createEntry('002', 'waiting', $this->payment);
        $call();
        $this->assertDatabaseHas('queue_entries', ['id' => $second->id, 'status' => 'waiting']);
    }

    private function createEntry(string $ticket, string $status, ?Purpose $purpose, string $purposeText = ''): QueueEntry
    {
        return QueueEntry::create([
            'ticket_number' => $ticket,
            'name' => 'Queue Guest',
            'purpose' => $purpose?->name ?: ($purposeText ?: 'Other'),
            'purpose_id' => $purpose?->id,
            'phone_number' => '09170000997',
            'status' => $status,
            'served_at' => $status === 'serving' ? now() : null,
            'department_id' => $this->department->id,
            'queue_date' => today(),
        ]);
    }
}
