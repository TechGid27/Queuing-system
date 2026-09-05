<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns came from already-applied migrations, so repair them in place.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE guests MODIFY phone_verified_at TIMESTAMP NULL DEFAULT NULL');
            DB::statement("ALTER TABLE guests MODIFY role VARCHAR(255) NOT NULL DEFAULT 'student'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'");
        }

        Schema::table('guests', function (Blueprint $table) {
            $table->unique('phone_number', 'guests_phone_number_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
            $table->unique('phone_number', 'users_phone_number_unique');
        });

        Schema::table('phone_otps', function (Blueprint $table) {
            $table->string('purpose')->default('guest_verification')->after('phone_number');
            $table->index(['phone_number', 'purpose'], 'phone_otps_phone_purpose_index');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->foreignId('guest_id')
                ->nullable()
                ->after('user_id')
                ->constrained('guests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            if (DB::table('users')->where('role', 'staff')->exists()) {
                throw new RuntimeException('Cannot restore the old users role enum while staff accounts exist.');
            }

            if (DB::table('guests')->whereNull('phone_verified_at')->exists()) {
                throw new RuntimeException('Cannot restore the old guests schema while pending verifications exist.');
            }
        }

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_id');
        });

        Schema::table('phone_otps', function (Blueprint $table) {
            $table->dropIndex('phone_otps_phone_purpose_index');
            $table->dropColumn('purpose');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique('guests_phone_number_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_phone_number_unique');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin') NOT NULL");
            DB::statement("ALTER TABLE guests MODIFY role VARCHAR(255) NOT NULL DEFAULT 'guest'");
            DB::statement('ALTER TABLE guests MODIFY phone_verified_at TIMESTAMP NOT NULL');
        }
    }
};
