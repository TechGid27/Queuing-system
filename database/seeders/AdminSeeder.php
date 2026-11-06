<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminSeeder extends Seeder
{
    /**
     * Create the administrator account.
     *
     * Credentials are read from the environment — nothing is hard-coded:
     *   ADMIN_NAME, ADMIN_EMAIL (required), ADMIN_PASSWORD (required, min 8),
     *   ADMIN_PHONE (optional)
     *
     * Uses firstOrCreate by email, so an existing admin's password is never
     * reset on deploy/restart. Skips with a warning when unset or invalid.
     *
     * @return void
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            $this->command->warn('AdminSeeder skipped: set ADMIN_EMAIL and ADMIN_PASSWORD to seed the administrator account.');

            return;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'min:8', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            $this->command->warn('AdminSeeder skipped: '.implode(' ', $validator->errors()->all()));

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME') ?: 'System Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'department_id' => null,
                'is_active' => true,
                'phone_number' => env('ADMIN_PHONE') ?: null,
                'phone_verified_at' => now(),
            ]
        );

        $this->command->info("Admin ready: {$user->email} (role: {$user->role})");
    }
}
