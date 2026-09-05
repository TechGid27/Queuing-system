<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 15);
            $table->string('otp', 6);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('phone_otps');
    }
};
