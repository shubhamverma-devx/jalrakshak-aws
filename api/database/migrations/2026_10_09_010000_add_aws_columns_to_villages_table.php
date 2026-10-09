<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AWS columns on villages.
 *
 * Each village gets its own Amazon SNS topic, so an officer alert reaches only
 * the people of that village. The inundation map is an object in Amazon S3, and
 * only the key is stored: the readable URL is presigned and short lived, so it
 * is generated on every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->string('sns_topic_arn')->nullable()->after('river_station_id');
            $table->string('inundation_map_path')->nullable()->after('sns_topic_arn');
            $table->timestamp('inundation_map_updated_at')->nullable()->after('inundation_map_path');
        });
    }

    public function down(): void
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->dropColumn(['sns_topic_arn', 'inundation_map_path', 'inundation_map_updated_at']);
        });
    }
};
