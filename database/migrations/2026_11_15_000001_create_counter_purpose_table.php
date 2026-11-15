<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_purpose', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purpose_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['counter_id', 'purpose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_purpose');
    }
};
