<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_clip_id')->constrained('video_clips')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('analysis_type', 80)->default('scout_mvp');
            $table->string('analysis_version', 40)->default('mock-v1');
            $table->json('summary')->nullable();
            $table->json('raw_output')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['video_clip_id', 'status']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_analyses');
    }
};
