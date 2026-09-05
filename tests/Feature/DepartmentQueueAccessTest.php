<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Guest;
use App\Models\Purpose;
use App\Models\QueueEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DepartmentQueueAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_for_a_specific_department(): void
    {
        $admin = $this->createUser('admin');
        $department = Department::create(['name' => 'Registrar']);

        $response = $this->actingAs($admin, 'web')->post(route('admin.staff.store'), [
            'department_id' => $department->id,
            'name' => 'Registrar Staff',
            'email' => 'registrar@example.com',
            'phone_number' => '09170000002',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'department_id' => $department->id,
            'email' => 'registrar@example.com',
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_and_view_departments(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin, 'web')
            ->post(route('admin.departments.store'), ['department_name' => 'Registrar'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['name' => 'Registrar']);
        $this->get(route('admin.departments.index'))->assertOk()->assertSee('Registrar');
        $this->get(route('admin.reports'))->assertOk();
    }

    public function test_staff_dashboard_only_contains_its_department_queue(): void
    {
        $cashier = Department::where('name', 'Cashier')->firstOrFail();
        $registrar = Department::create(['name' => 'Registrar']);
        $staff = $this->createUser('staff', $cashier);
        $this->createQueueEntry($cashier, 'CASHIER-STUDENT');
        $this->createQueueEntry($registrar, 'REGISTRAR-STUDENT');

        $response = $this->actingAs($staff, 'web')->get(route('admin.index', [
            'department_id' => $registrar->id,
        ]));

        $response->assertOk();
        $response->assertSee('CASHIER-STUDENT');
        $response->assertDontSee('REGISTRAR-STUDENT');
    }

    public function test_staff_cannot_control_another_department_ticket(): void
    {
        $cashier = Department::where('name', 'Cashier')->firstOrFail();
        $registrar = Department::create(['name' => 'Registrar']);
        $staff = $this->createUser('staff', $cashier);
        $ticket = $this->createQueueEntry($registrar, 'REGISTRAR-STUDENT', 'serving');

        $this->actingAs($staff, 'web')
            ->post(route('admin.complete', $ticket))
            ->assertForbidden();

        $this->assertDatabaseHas('queue_entries', [
            'id' => $ticket->id,
            'status' => 'serving',
        ]);
    }

    public function test_deactivated_staff_cannot_access_department_queue(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', $department, false);

        $this->actingAs($staff, 'web')
            ->get(route('admin.index'))
            ->assertRedirect(route('login'));
    }

    public function test_deactivated_department_rejects_new_tickets(): void
    {
        $department = Department::create([
            'name' => 'Inactive Office',
            'is_active' => false,
        ]);
        $guest = Guest::create([
            'student_id' => '2026-00999',
            'phone_number' => '09170000009',
            'phone_verified_at' => now(),
            'role' => 'student',
        ]);
        $purpose = Purpose::create([
            'name' => 'Inquiry',
            'is_active' => true,
        ]);

        $response = $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $department->id,
            'purpose_id' => $purpose->id,
        ]);

        $response->assertSessionHasErrors('department_id');
        $this->assertDatabaseCount('queue_entries', 0);
    }

    public function test_deactivating_department_pauses_queue_and_blocks_assigned_staff(): void
    {
        Event::fake([\App\Events\QueueUpdated::class]);
        $admin = $this->createUser('admin');
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', $department);

        $this->actingAs($admin, 'web')->patch(route('admin.departments.status', $department), [
            'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'is_active' => false,
            'queue_paused' => true,
        ]);
        $this->actingAs($staff, 'web')->get(route('admin.index'))->assertRedirect(route('login'));
        Event::assertDispatched(\App\Events\QueueUpdated::class);
    }

    public function test_inactive_department_staff_is_logged_out_on_the_login_page(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $department->update(['is_active' => false]);
        $staff = $this->createUser('staff', $department);

        $this->actingAs($staff, 'web')->get(route('login'))->assertOk();

        $this->assertGuest('web');
    }

    public function test_public_queue_status_does_not_expose_personal_information(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $this->createQueueEntry($department, 'PRIVATE-CURRENT-NAME', 'serving');
        $this->createQueueEntry($department, 'PRIVATE-WAITING-NAME');

        $response = $this->getJson(route('api.queueStatus', ['department_id' => $department->id]));

        $response->assertOk()
            ->assertJsonPath('department_id', $department->id)
            ->assertJsonStructure([
                'current_serving' => ['ticket_number'],
                'waiting_list' => [['ticket_number', 'position']],
            ]);
        $this->assertStringNotContainsString('PRIVATE-', $response->getContent());
        $this->assertStringNotContainsString('0918', $response->getContent());
        $this->assertStringNotContainsString('purpose', strtolower($response->getContent()));
    }

    public function test_ticket_sequence_uses_existing_department_tickets(): void
    {
        Event::fake();
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $this->createQueueEntry($department, 'EXISTING')->update(['ticket_number' => '007']);
        $guest = Guest::create([
            'student_id' => '2026-00888',
            'phone_number' => '09170000888',
            'phone_verified_at' => now(),
            'role' => 'student',
        ]);
        $purpose = Purpose::create([
            'name' => 'Enrollment',
            'is_active' => true,
        ]);

        $this->actingAs($guest, 'student')->post(route('queue.store'), [
            'department_id' => $department->id,
            'purpose_id' => $purpose->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('queue_entries', [
            'department_id' => $department->id,
            'guest_id' => $guest->id,
            'ticket_number' => '008',
        ]);
    }

    public function test_lunch_scheduler_only_resumes_queues_that_it_paused(): void
    {
        Event::fake([\App\Events\QueueUpdated::class]);
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        $manual = Department::where('name', 'Cashier')->firstOrFail();
        $manual->update(['queue_paused' => true, 'lunch_break_paused' => false]);
        $automatic = Department::create(['name' => 'Registrar']);
        DB::table('settings')->where('key', 'lunch_break_start')->update(['value' => '12:00']);
        DB::table('settings')->where('key', 'lunch_break_end')->update(['value' => '13:00']);

        Carbon::setTestNow('2026-09-06 12:00:00');
        $this->artisan('queue:lunch-break')->assertExitCode(0);
        $this->assertTrue($automatic->fresh()->queue_paused);
        $this->assertTrue($automatic->fresh()->lunch_break_paused);
        $this->assertFalse($manual->fresh()->lunch_break_paused);

        Carbon::setTestNow('2026-09-06 13:00:00');
        $this->artisan('queue:lunch-break')->assertExitCode(0);
        $this->assertFalse($automatic->fresh()->queue_paused);
        $this->assertFalse($automatic->fresh()->lunch_break_paused);
        $this->assertTrue($manual->fresh()->queue_paused);

        Carbon::setTestNow();
    }

    private function createUser(string $role, ?Department $department = null, bool $active = true): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'department_id' => $department?->id,
            'name' => ucfirst($role).' User',
            'email' => "{$role}{$sequence}@example.com",
            'phone_number' => '0917'.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT),
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    private function createQueueEntry(
        Department $department,
        string $name,
        string $status = 'waiting'
    ): QueueEntry {
        static $sequence = 0;
        $sequence++;

        return QueueEntry::create([
            'department_id' => $department->id,
            'queue_date' => today(),
            'ticket_number' => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'name' => $name,
            'purpose' => 'Test',
            'phone_number' => '0918'.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT),
            'status' => $status,
            'served_at' => $status === 'serving' ? now() : null,
        ]);
    }
}
