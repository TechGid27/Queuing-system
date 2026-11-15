<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_admin_visiting_login_page_goes_to_overview(): void
    {
        $admin = $this->createUser('admin', 'redirect-admin@example.com');

        $this->actingAs($admin, 'web')
            ->get(route('login'))
            ->assertRedirect(route('admin.overview'));
    }

    public function test_logged_in_staff_visiting_login_page_goes_to_queue_console(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', 'redirect-staff@example.com', $department);

        $this->actingAs($staff, 'web')
            ->get(route('login'))
            ->assertRedirect(route('admin.index'));
    }

    public function test_admin_login_ignores_stale_console_intended_url(): void
    {
        $admin = $this->createUser('admin', 'intended-admin@example.com');

        $this->withSession(['url.intended' => url('/admin')])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => 'password123',
            ])
            ->assertRedirect(route('admin.overview'));

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_staff_login_keeps_console_intended_url(): void
    {
        $department = Department::where('name', 'Cashier')->firstOrFail();
        $staff = $this->createUser('staff', 'intended-staff@example.com', $department);

        $this->withSession(['url.intended' => url('/admin')])
            ->post(route('login.post'), [
                'email' => $staff->email,
                'password' => 'password123',
            ])
            ->assertRedirect(url('/admin'));
    }

    public function test_admin_login_keeps_deep_link_intended_url(): void
    {
        $admin = $this->createUser('admin', 'deep-admin@example.com');

        $this->withSession(['url.intended' => url('/admin/audit-log')])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => 'password123',
            ])
            ->assertRedirect(url('/admin/audit-log'));
    }

    private function createUser(string $role, string $email, ?Department $department = null): User
    {
        return User::create([
            'department_id' => $department?->id,
            'name' => ucfirst($role).' Redirect User',
            'email' => $email,
            'phone_number' => $role === 'admin' ? '09170000061' : '09170000062',
            'phone_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
