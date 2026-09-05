<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed the default staff invite code
        DB::table('settings')->insert([
            [
                'key'        => 'staff_invite_code',
                'value'      => 'ACLC-STAFF-2026',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'queue_paused',
                'value'      => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'lunch_break_start',
                'value'      => '12:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'lunch_break_end',
                'value'      => '13:30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
