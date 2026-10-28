<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->boolean('queue_paused')->default(false)->after('is_active');
            $table->boolean('auto_pause_enabled')->default(true)->after('queue_paused');
            $table->boolean('lunch_break_paused')->default(false)->after('auto_pause_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->dropColumn(['queue_paused', 'auto_pause_enabled', 'lunch_break_paused']);
        });
    }
};
