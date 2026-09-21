<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speed up per-department daily queue lookups (queueState, broadcast,
     * auto-skip, reports). All hot paths filter by department + date + status.
     */
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->index(['department_id', 'queue_date', 'status'], 'idx_qe_dept_date_status');
            $table->index(['guest_id', 'queue_date', 'status'], 'idx_qe_guest_date_status');
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropIndex('idx_qe_dept_date_status');
            $table->dropIndex('idx_qe_guest_date_status');
        });
    }
};
