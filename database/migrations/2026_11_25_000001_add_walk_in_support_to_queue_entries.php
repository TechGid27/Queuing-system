<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Walk-ins have no phone; drop NOT NULL on databases migrated before
        // the create-table change (fresh installs already get nullable).
        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE queue_entries MODIFY phone_number VARCHAR(15) NULL'),
            'pgsql' => DB::statement('ALTER TABLE queue_entries ALTER COLUMN phone_number DROP NOT NULL'),
            default => null,
        };

        Schema::table('queue_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_entries', 'source')) {
                $table->string('source', 20)->default('online')->after('phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            if (Schema::hasColumn('queue_entries', 'source')) {
                $table->dropColumn('source');
            }
        });

        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE queue_entries MODIFY phone_number VARCHAR(15) NOT NULL'),
            'pgsql' => DB::statement('ALTER TABLE queue_entries ALTER COLUMN phone_number SET NOT NULL'),
            default => null,
        };
    }
};
