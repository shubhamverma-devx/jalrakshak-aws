<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->decimal('rainfall_mm', 6, 1);   // rainfall in the last 24 hours
            $table->decimal('water_level_m', 6, 2); // river or water level
            $table->string('source')->default('manual');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['zone_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
