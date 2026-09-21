<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the default administrator account.
     * Safe to re-run: uses updateOrCreate by email.
     *
     * Customize via env (optional):
     *   ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_PHONE
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@aclc.edu.ph')],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin1234')),
                'role' => 'admin',
                'department_id' => null, // admin oversees all departments
                'is_active' => true,
                'phone_number' => env('ADMIN_PHONE', '09123456789'),
                'phone_verified_at' => now(),
            ]
        );

        $this->command->info("Admin ready: {$user->email} (role: {$user->role})");
    }
}
