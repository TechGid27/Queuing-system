<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\Guest;
use App\Models\Purpose;
use App\Models\QueueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StudentWindowDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Purpose $enrolment;

    private Purpose $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['name' => 'Guidance']);
        $this->department->counters()->create(['name' => 'Window 2']);

        $this->enrolment = Purpose::create(['name' => 'Enrolment', 'is_active' => true]);
        $this->inquiry = Purpose::create(['name' => 'Inquiry', 'is_active' => true]);

        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();
        $window2->purposes()->attach($this->enrolment->id);
    }

    private function guest(): Guest
    {
        return Guest::create([
            'student_id' => '2026-00777',
            'phone_number' => '09170007777',
            'phone_verified_at' => now(),
            'role' => 'student',
        ]);
    }

    private function joinQueue(Guest $guest, Purpose $purpose): void
    {
        Event::fake([QueueUpdated::class]);

        $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $this->department->id,
            'purpose_id' => $purpose->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_waiting_ticket_shows_the_window_that_serves_its_purpose(): void
    {
        $guest = $this->guest();
        $this->joinQueue($guest, $this->enrolment);

        $response = $this->actingAs($guest, 'student')
            ->get(route('student.index', ['department_id' => $this->department->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="my-ticket-window".*?Served at.*?Window 2/s',
            $response->getContent()
        );
    }

    public function test_unmatched_purpose_ticket_shows_the_overflow_window(): void
    {
        $guest = $this->guest();

        QueueEntry::create([
            'ticket_number' => '001',
            'name' => $guest->student_id,
            'purpose' => $this->inquiry->name,
            'purpose_id' => $this->inquiry->id,
            'phone_number' => $guest->phone_number,
            'status' => 'waiting',
            'department_id' => $this->department->id,
            'guest_id' => $guest->id,
            'queue_date' => today(),
        ]);

        $response = $this->actingAs($guest, 'student')
            ->get(route('student.index', ['department_id' => $this->department->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="my-ticket-window".*?Window 2/s',
            $response->getContent()
        );

        $this->actingAs($guest, 'student')
            ->getJson(route('api.queueStatus', ['department_id' => $this->department->id]))
            ->assertOk()
            ->assertJsonPath('my_ticket.windows', ['Window 2']);
    }

    public function test_unassigned_and_other_purposes_go_to_the_window_with_fewest_purposes(): void
    {
        Event::fake([QueueUpdated::class]);
        $guest = $this->guest();

        // Window 2 (1 purpose) is the only/lightest window → takes unassigned and "Other".
        $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $this->department->id,
            'purpose_id' => $this->inquiry->id,
        ])->assertSessionHasNoErrors();

        $guest2 = Guest::create([
            'student_id' => '2026-00779',
            'phone_number' => '09170007779',
            'phone_verified_at' => now(),
            'role' => 'student',
        ]);
        $this->actingAs($guest2, 'student')->post(route('queue.store'), [
            'department_id' => $this->department->id,
            'purpose_id' => 'other',
            'other_purpose' => 'Scholarship form',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('queue_entries', 2);
    }

    public function test_join_with_a_matched_purpose_succeeds(): void
    {
        Event::fake([QueueUpdated::class]);
        $guest = $this->guest();

        $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $this->department->id,
            'purpose_id' => $this->enrolment->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('queue_entries', 1);
    }

    public function test_unmatched_purpose_falls_back_to_a_general_window(): void
    {
        // A general window (no assigned purposes) can take any purpose.
        $this->department->counters()->create(['name' => 'Window 3']);

        $guest = $this->guest();
        $this->joinQueue($guest, $this->inquiry);

        $response = $this->actingAs($guest, 'student')
            ->get(route('student.index', ['department_id' => $this->department->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="my-ticket-window".*?Served at.*?Window 3/s',
            $response->getContent()
        );
    }

    public function test_serving_ticket_shows_the_window_serving_it(): void
    {
        $guest = $this->guest();
        $window2 = $this->department->counters()->where('name', 'Window 2')->firstOrFail();

        QueueEntry::create([
            'ticket_number' => '001',
            'name' => $guest->student_id,
            'purpose' => 'Enrolment',
            'phone_number' => $guest->phone_number,
            'status' => 'serving',
            'department_id' => $this->department->id,
            'counter_id' => $window2->id,
            'purpose_id' => $this->enrolment->id,
            'guest_id' => $guest->id,
            'queue_date' => today(),
        ]);

        $response = $this->actingAs($guest, 'student')
            ->get(route('student.index', ['department_id' => $this->department->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="my-ticket-window".*?Proceed to.*?Window 2/s',
            $response->getContent()
        );
    }

    public function test_queue_status_api_includes_the_windows_for_the_guest_ticket(): void
    {
        $guest = $this->guest();
        $this->joinQueue($guest, $this->enrolment);

        $this->actingAs($guest, 'student')
            ->getJson(route('api.queueStatus', ['department_id' => $this->department->id]))
            ->assertOk()
            ->assertJsonPath('my_ticket.status', 'waiting')
            ->assertJsonPath('my_ticket.windows', ['Window 2']);
    }

    public function test_join_form_exposes_the_purpose_to_window_map(): void
    {
        $guest = $this->guest();

        $response = $this->actingAs($guest, 'student')
            ->get(route('student.index', ['department_id' => $this->department->id]))
            ->assertOk()
            ->assertSee('id="purpose-window-hint"', false);

        $this->assertStringContainsString(
            '"'.$this->enrolment->id.'":["Window 2"]',
            $response->getContent()
        );
    }
}
