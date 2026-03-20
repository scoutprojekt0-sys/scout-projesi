<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('author_role', 30);
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_role', 30);
            $table->enum('relationship_type', [
                'birlikte_calistik',
                'rakiptik',
                'izledim',
                'temsil_sureci',
                'kulup_sureci',
                'teknik_ekip',
            ]);
            $table->enum('sentiment', ['olumlu', 'notr', 'dikkat']);
            $table->text('body');
            $table->enum('status', ['published', 'reported', 'under_review', 'removed'])->default('published');
            $table->timestamps();

            $table->unique(['author_id', 'target_id']);
            $table->index(['target_id', 'status', 'created_at']);
        });

        Schema::create('profile_review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('profile_reviews')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->unique('review_id');
            $table->index('author_id');
        });

        Schema::create('profile_review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('profile_reviews')->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 80);
            $table->timestamps();

            $table->unique(['review_id', 'reported_by']);
            $table->index(['review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_review_reports');
        Schema::dropIfExists('profile_review_replies');
        Schema::dropIfExists('profile_reviews');
    }
};
