<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number');
            $table->string('name');
            $table->string('purpose');
            $table->string('phone_number', 15);
            $table->enum('status', ['waiting', 'serving', 'completed', 'no_response', 'skipped'])->default('waiting');
            $table->timestamp('served_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('queue_entries');
    }
};
