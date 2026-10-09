<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('risk_level');
            $table->text('message');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->string('channel')->default('sns-email');
            $table->string('sns_message_id')->nullable();
            $table->string('delivery_status')->default('sent');
            $table->text('delivery_note')->nullable();
            $table->string('triggered_by')->default('officer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
