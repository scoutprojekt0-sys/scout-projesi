<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_match_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_match_id')->constrained('live_matches')->cascadeOnDelete();
            $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('club_internal_player_id')->constrained('club_internal_players')->cascadeOnDelete();
            $table->string('sport', 40);
            $table->string('position', 120)->nullable();
            $table->unsignedSmallInteger('minutes_played')->default(0);
            $table->decimal('base_score', 5, 2)->default(6.00);
            $table->decimal('positive_score', 6, 2)->default(0);
            $table->decimal('negative_score', 6, 2)->default(0);
            $table->decimal('final_rating', 4, 2);
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->unique(['live_match_id', 'club_internal_player_id'], 'pmr_match_player_unique');
            $table->index(['club_internal_player_id', 'created_at'], 'pmr_player_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_match_ratings');
    }
};
