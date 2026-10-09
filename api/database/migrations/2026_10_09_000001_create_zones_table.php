<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('district');
            $table->string('river')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->unsignedInteger('population')->default(0);

            // Per-zone thresholds used by the rule based risk engine.
            $table->decimal('warning_level_m', 6, 2);
            $table->decimal('danger_level_m', 6, 2);

            // Inundation map stored in Amazon S3. Only the object key is kept:
            // the readable URL is presigned and short lived, so it is generated
            // on every read rather than stored.
            $table->string('inundation_map_path')->nullable();
            $table->timestamp('inundation_map_updated_at')->nullable();

            // Amazon SNS topic that alerts for this zone fan out to.
            $table->string('sns_topic_arn')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
