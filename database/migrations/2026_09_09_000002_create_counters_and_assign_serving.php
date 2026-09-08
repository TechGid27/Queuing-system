<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        // Seed one default counter per existing department (backward compatible single-lane).
        DB::table('departments')->orderBy('id')->eachById(function ($department) {
            DB::table('counters')->updateOrInsert(
                ['department_id' => $department->id, 'name' => 'Window 1'],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->foreignId('counter_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('served_by')->nullable()->after('counter_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_id');
            $table->dropConstrainedForeignId('served_by');
        });

        Schema::dropIfExists('counters');
    }
};
