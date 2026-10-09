<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('phone')->nullable();

            // Amazon SNS subscription for this email on the zone topic.
            $table->string('sns_subscription_arn')->nullable();
            $table->string('sns_status')->default('pending');

            $table->timestamps();

            $table->unique(['zone_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
