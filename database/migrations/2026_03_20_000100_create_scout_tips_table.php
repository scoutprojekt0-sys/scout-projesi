<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('video_clip_id')->nullable()->constrained('video_clips')->nullOnDelete();
            $table->foreignId('duplicate_of_tip_id')->nullable()->constrained('scout_tips')->nullOnDelete();
            $table->enum('status', ['pending', 'screened', 'shortlisted', 'approved', 'rejected', 'withdrawn', 'trial', 'signed', 'rewarded'])->default('pending');
            $table->enum('source_type', ['new_player', 'existing_player'])->default('new_player');
            $table->string('player_name', 160);
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('position', 60)->nullable();
            $table->string('foot', 20)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('city', 80);
            $table->string('district', 80)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('team_name', 160)->nullable();
            $table->string('competition_level', 80)->nullable();
            $table->date('match_date')->nullable();
            $table->enum('guardian_consent_status', ['not_required', 'pending', 'received', 'rejected'])->default('pending');
            $table->text('description');
            $table->decimal('ai_quality_score', 5, 2)->default(0);
            $table->decimal('review_score', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('screened_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('trial_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['submitted_by', 'status']);
            $table->index(['status', 'final_score']);
            $table->index(['player_name', 'birth_year', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_tips');
    }
};
