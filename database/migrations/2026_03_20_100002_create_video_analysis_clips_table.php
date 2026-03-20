<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_analysis_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_analysis_event_id')->constrained('video_analysis_events')->cascadeOnDelete();
            $table->string('clip_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->unsignedInteger('clip_start_second')->nullable();
            $table->unsignedInteger('clip_end_second')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_analysis_clips');
    }
};
