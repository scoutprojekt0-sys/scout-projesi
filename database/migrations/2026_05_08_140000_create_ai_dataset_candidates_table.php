<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_dataset_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sport', 40);
            $table->string('status', 40)->default('queued');
            $table->string('split', 16)->nullable();
            $table->string('source_type', 32)->default('user_upload');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('labeling_started_at')->nullable();
            $table->timestamp('labeled_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model_version', 120)->nullable();
            $table->timestamps();

            $table->unique('video_clip_id');
            $table->index(['sport', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dataset_candidates');
    }
};
