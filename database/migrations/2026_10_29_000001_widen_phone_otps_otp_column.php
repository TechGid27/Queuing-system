<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // OTPs are now stored as password hashes, which need more than six
        // characters. SQLite does not enforce VARCHAR lengths, so it needs no
        // alteration.
        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE phone_otps MODIFY otp VARCHAR(255) NOT NULL'),
            'pgsql' => DB::statement('ALTER TABLE phone_otps ALTER COLUMN otp TYPE VARCHAR(255)'),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE phone_otps MODIFY otp VARCHAR(6) NOT NULL'),
            'pgsql' => DB::statement('ALTER TABLE phone_otps ALTER COLUMN otp TYPE VARCHAR(6)'),
            default => null,
        };
    }
};
