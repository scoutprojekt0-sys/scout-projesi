<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_training_runs')) {
            Schema::create('ai_training_runs', function (Blueprint $table) {
                $table->id();
                $table->string('sport', 40);
                $table->string('status', 40)->default('queued');
                $table->string('model_version', 120);
                $table->string('device', 32)->default('cpu');
                $table->unsignedInteger('epochs')->default(60);
                $table->unsignedInteger('imgsz')->default(960);
                $table->unsignedInteger('batch')->default(8);
                $table->boolean('forced')->default(false);
                $table->unsignedInteger('candidate_count')->default(0);
                $table->json('candidate_ids')->nullable();
                $table->text('notes')->nullable();
                $table->longText('output_log')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->index(['sport', 'status']);
                $table->unique('model_version');
            });
        }

        if (! Schema::hasTable('ai_dataset_candidate_training_run')) {
            Schema::create('ai_dataset_candidate_training_run', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_dataset_candidate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ai_training_run_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['ai_dataset_candidate_id', 'ai_training_run_id'], 'ai_candidate_run_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dataset_candidate_training_run');
        Schema::dropIfExists('ai_training_runs');
    }
};
