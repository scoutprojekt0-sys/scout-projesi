<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_video_metrics', function (Blueprint $table) {
            $table->unsignedSmallInteger('assist_vision_score')->default(0)->after('cross_quality_score');
            $table->unsignedSmallInteger('drive_efficiency_score')->default(0)->after('assist_vision_score');
            $table->unsignedSmallInteger('spike_quality_score')->default(0)->after('drive_efficiency_score');
            $table->unsignedSmallInteger('block_timing_score')->default(0)->after('spike_quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('player_video_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'assist_vision_score',
                'drive_efficiency_score',
                'spike_quality_score',
                'block_timing_score',
            ]);
        });
    }
};
