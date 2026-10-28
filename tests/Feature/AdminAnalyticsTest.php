<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_analytics_page(): void
    {
        $admin = $this->createUser('admin', 'analytics-admin@example.com', null);

        $this->actingAs($admin, 'web')
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Queue Analytics')
            ->assertSee('Hourly queue volume')
            ->assertSee('All departments')
            ->assertSee('chart.js');
    }

    public function test_analytics_data_aggregates_tickets_for_the_selected_range(): void
    {
        $admin = $this->createUser('admin', 'analytics-data@example.com', null);
        $cashier = Department::where('name', 'Cashier')->firstOrFail();
        $registrar = Department::create(['name' => 'Registrar']);

        $this->createEntry($cashier, '001', 'completed', now()->subMinutes(20), now()->subMinutes(10), now()->subMinutes(5));
        $this->createEntry($cashier, '002', 'waiting', now());
        $this->createEntry($registrar, '003', 'no_response', now()->subMinutes(15), now()->subMinutes(5), now()->subMinutes(2));

        $response = $this->actingAs($admin, 'web')
            ->getJson(route('admin.analytics.data', ['range' => 'today']));

        $response->assertOk()
            ->assertJsonPath('range.days', 1)
            ->assertJsonPath('kpis.total', 3)
            ->assertJsonPath('kpis.completed', 1)
            ->assertJsonPath('kpis.no_response', 1)
            ->assertJsonPath('kpis.waiting', 1)
            ->assertJsonPath('kpis.serving', 0)
            ->assertJsonPath('kpis.avg_wait_minutes', 10.0)
            ->assertJsonPath('kpis.avg_service_minutes', 5.0)
            ->assertJsonPath('kpis.no_show_rate', 33.3)
            ->assertJsonPath('statuses.data', [1, 1, 1, 0])
            ->assertJsonPath('trend.total.0', 3)
            ->assertJsonPath('trend.completed.0', 1)
            ->assertJsonPath('trend.no_response.0', 1)
            ->assertJsonPath('trend.avg_wait.0', 10.0)
            ->assertJsonPath('trend.avg_service.0', 5.0)
            ->assertJsonPath('departments.labels', ['Cashier', 'Registrar'])
            ->assertJsonPath('departments.completed', [1, 0])
            ->assertJsonPath('departments.no_response', [0, 1])
            ->assertJsonPath('departments.pending', [1, 0])
            ->assertJsonPath('purposes.labels', ['Inquiry'])
            ->assertJsonPath('purposes.data', [3]);

        $this->assertSame(3, array_sum($response->json('hourly.data')));
    }

    public function test_analytics_data_can_be_filtered_by_department(): void
    {
        $admin = $this->createUser('admin', 'analytics-filter@example.com', null);
        $cashier = Department::where('name', 'Cashier')->firstOrFail();
        $registrar = Department::create(['name' => 'Registrar']);

        $this->createEntry($cashier, '001', 'completed', now()->subMinutes(20), now()->subMinutes(10), now()->subMinutes(5));
        $this->createEntry($registrar, '002', 'no_response', now()->subMinutes(15), now()->subMinutes(5), now()->subMinutes(2));

        $this->actingAs($admin, 'web')
            ->getJson(route('admin.analytics.data', [
                'range' => 'today',
                'department_id' => $registrar->id,
            ]))
            ->assertOk()
            ->assertJsonPath('kpis.total', 1)
            ->assertJsonPath('kpis.completed', 0)
            ->assertJsonPath('kpis.no_response', 1)
            ->assertJsonPath('departments.labels', ['Registrar']);
    }

    public function test_analytics_data_rejects_invalid_filters(): void
    {
        $admin = $this->createUser('admin', 'analytics-invalid@example.com', null);

        $this->actingAs($admin, 'web')
            ->getJson(route('admin.analytics.data', ['range' => 'bogus']))
            ->assertStatus(422);
    }

    public function test_staff_cannot_access_admin_analytics(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', 'analytics-staff@example.com', $department);

        $this->actingAs($staff, 'web')
            ->get(route('admin.analytics'))
            ->assertRedirect(route('admin.index'));
    }

    public function test_guests_are_redirected_to_login_for_analytics(): void
    {
        $this->get(route('admin.analytics'))
            ->assertRedirect(route('login'));
    }

    private function createUser(string $role, string $email, ?Department $department): User
    {
        return User::create([
            'department_id' => $department?->id,
            'name' => ucfirst($role).' User',
            'email' => $email,
            'phone_number' => $role === 'admin' ? '09170000041' : '09170000042',
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
        Carbon $createdAt,
        ?Carbon $servedAt = null,
        ?Carbon $completedAt = null
    ): QueueEntry {
        $entry = QueueEntry::create([
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

        DB::table('queue_entries')->where('id', $entry->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $entry;
    }
}
