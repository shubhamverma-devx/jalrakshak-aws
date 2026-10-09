<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citizens who asked to be warned about a village, by email through Amazon SNS.
 *
 * This sits alongside device_tokens rather than replacing it. Device tokens are
 * the Android push path; this is the web path, which works without an app
 * install and needs no DLT registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('phone')->nullable();

            $table->string('sns_subscription_arn')->nullable();
            $table->string('sns_status')->default('pending');

            $table->timestamps();

            $table->unique(['village_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
