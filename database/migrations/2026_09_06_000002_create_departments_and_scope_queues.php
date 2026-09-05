<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('queue_paused')->default(false);
            $table->boolean('lunch_break_paused')->default(false);
            $table->timestamps();
        });

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Cashier',
            'is_active' => true,
            'queue_paused' => DB::table('settings')->where('key', 'queue_paused')->value('value') === '1',
            'lunch_break_paused' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('guest_id')->constrained()->nullOnDelete();
            $table->date('queue_date')->nullable()->after('department_id');
        });

        DB::table('users')->where('role', 'staff')->update(['department_id' => $departmentId]);

        DB::table('queue_entries')
            ->orderBy('id')
            ->eachById(function ($entry) use ($departmentId) {
                DB::table('queue_entries')->where('id', $entry->id)->update([
                    'department_id' => $departmentId,
                    'queue_date' => date('Y-m-d', strtotime($entry->created_at)),
                ]);
            });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->unique(
                ['department_id', 'queue_date', 'ticket_number'],
                'queue_entries_department_daily_ticket_unique'
            );
            $table->index(
                ['department_id', 'queue_date', 'status', 'id'],
                'queue_entries_department_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropUnique('queue_entries_department_daily_ticket_unique');
            $table->dropIndex('queue_entries_department_status_index');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('queue_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('departments');
    }
};
