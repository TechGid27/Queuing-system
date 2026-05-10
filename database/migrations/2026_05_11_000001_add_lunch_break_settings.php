<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert lunch break defaults only if they don't already exist
        $rows = [
            ['key' => 'lunch_break_start', 'value' => '12:00'],
            ['key' => 'lunch_break_end',   'value' => '13:30'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['lunch_break_start', 'lunch_break_end'])->delete();
    }
};
