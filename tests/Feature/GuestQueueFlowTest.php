<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\Guest;
use App\Models\PhoneOtp;
use App\Models\Purpose;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class GuestQueueFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_registration_creates_a_pending_guest_and_otp(): void
    {
        config(['services.recaptcha.secret' => null]);
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendOtp')->once();
        });

        $response = $this->post(route('register.post'), [
            'student_id' => '2026-00123',
            'phone_number' => '09171234567',
        ]);

        $response->assertRedirect(route('student.verify.show'));
        $response->assertSessionHas('otp_account_type', 'guest');
        $response->assertSessionHas('otp_phone', '09171234567');

        $this->assertDatabaseHas('guests', [
            'student_id' => '2026-00123',
            'phone_number' => '09171234567',
            'phone_verified_at' => null,
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('phone_otps', [
            'phone_number' => '09171234567',
            'purpose' => 'guest_verification',
        ]);

        $this->get(route('student.verify.show'))
            ->assertOk()
            ->assertSee('09171234567');
    }

    public function test_guest_otp_verification_logs_into_the_student_guard(): void
    {
        $guest = Guest::create([
            'student_id' => '2026-00123',
            'phone_number' => '09171234567',
            'role' => 'student',
        ]);
        PhoneOtp::create([
            'phone_number' => $guest->phone_number,
            'purpose' => 'guest_verification',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post(route('student.verify'), [
            'phone' => $guest->phone_number,
            'account_type' => 'guest',
            'otp' => '123456',
        ]);

        $response->assertRedirect(route('student.index'));
        $this->assertAuthenticatedAs($guest, 'student');
        $this->assertGuest('web');
        $this->assertNotNull($guest->fresh()->phone_verified_at);
        $this->assertDatabaseMissing('phone_otps', [
            'phone_number' => $guest->phone_number,
            'purpose' => 'guest_verification',
        ]);
    }

    public function test_verified_guest_can_join_the_queue_with_guest_ownership(): void
    {
        Event::fake([QueueUpdated::class]);
        $guest = Guest::create([
            'student_id' => '2026-00123',
            'phone_number' => '09171234567',
            'phone_verified_at' => now(),
            'role' => 'student',
        ]);
        $purpose = Purpose::create([
            'name' => 'Enrollment',
            'is_active' => true,
        ]);
        $department = Department::where('name', 'Cashier')->firstOrFail();

        $response = $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $department->id,
            'purpose_id' => $purpose->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('queue_entries', [
            'guest_id' => $guest->id,
            'user_id' => null,
            'department_id' => $department->id,
            'name' => '2026-00123',
            'phone_number' => '09171234567',
            'purpose_id' => $purpose->id,
            'status' => 'waiting',
        ]);
        Event::assertDispatched(QueueUpdated::class);
        $this->get(route('student.index', ['department_id' => $department->id]))->assertOk();
    }

    public function test_public_and_private_registration_routes_have_distinct_actions(): void
    {
        $this->assertStringEndsWith('/verify', route('register.post'));
        $this->assertStringEndsWith('/private/register', route('private_register.post'));
    }
}
