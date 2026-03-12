<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contributions', function (Blueprint $table) {
            $table->id();

            // Contributor
            $table->unsignedBigInteger('user_id');

            // Target entity
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable(); // null if new entity

            // Contribution type
            $table->enum('contribution_type', [
                'create',
                'update',
                'correction',
                'add_source',
                'add_proof',
                'flag_error'
            ])->default('update');

            // Changes
            $table->json('proposed_data')->nullable();
            $table->json('current_data')->nullable();
            $table->text('description');

            // Evidence
            $table->string('source_url')->nullable();
            $table->json('proof_urls')->nullable(); // Multiple evidence links
            $table->text('reasoning')->nullable();

            // Status tracking
            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
                'needs_info'
            ])->default('pending');

            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_feedback')->nullable();

            // Quality score
            $table->decimal('quality_score', 3, 2)->default(0.5);

            // Flags
            $table->boolean('is_controversial')->default(false);
            $table->boolean('requires_expert_review')->default(false);

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['model_type', 'model_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contributions');
    }
};
