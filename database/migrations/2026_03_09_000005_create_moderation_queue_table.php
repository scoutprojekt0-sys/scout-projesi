<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_queue', function (Blueprint $table) {
            $table->id();

            // Target entity
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // Moderation details
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('reason', [
                'new_entry',
                'major_change',
                'conflict_detected',
                'missing_source',
                'low_confidence',
                'user_report',
                'automated_flag'
            ])->default('new_entry');

            // Changes
            $table->json('proposed_changes')->nullable();
            $table->json('current_values')->nullable();
            $table->text('change_description')->nullable();

            // Source & quality
            $table->string('source_url')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.5);

            // Submitter
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->useCurrent();

            // Reviewer
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();

            // Flags
            $table->boolean('requires_dual_approval')->default(false);
            $table->unsignedBigInteger('second_reviewer_id')->nullable();
            $table->timestamp('second_review_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('second_reviewer_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['status', 'priority']);
            $table->index(['model_type', 'model_id']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_queue');
    }
};
