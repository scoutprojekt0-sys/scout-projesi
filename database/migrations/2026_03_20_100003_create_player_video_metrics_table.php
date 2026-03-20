<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_video_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('video_analysis_id')->constrained('video_analyses')->cascadeOnDelete();
            $table->unsignedInteger('passes')->default(0);
            $table->unsignedInteger('successful_passes')->default(0);
            $table->unsignedInteger('cross_attempts')->default(0);
            $table->unsignedInteger('successful_crosses')->default(0);
            $table->unsignedInteger('shots')->default(0);
            $table->unsignedInteger('dribbles')->default(0);
            $table->unsignedInteger('ball_recoveries')->default(0);
            $table->unsignedSmallInteger('movement_score')->default(0);
            $table->unsignedSmallInteger('speed_score')->default(0);
            $table->unsignedSmallInteger('cross_quality_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'successful_crosses']);
            $table->index(['video_analysis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_video_metrics');
    }
};
