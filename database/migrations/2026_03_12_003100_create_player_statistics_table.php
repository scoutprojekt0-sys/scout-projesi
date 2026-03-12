<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('club_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('season', 20); // 2025-26
            $table->string('league', 100)->nullable(); // Premier League, La Liga, etc.
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_started')->default(0);
            $table->unsignedInteger('matches_benched')->default(0);
            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('yellow_cards')->default(0);
            $table->unsignedInteger('red_cards')->default(0);
            $table->unsignedInteger('minutes_played')->default(0);
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->json('metadata')->nullable(); // additional stats
            $table->timestamps();

            $table->unique(['user_id', 'club_id', 'season']);
            $table->index(['user_id', 'season']);
            $table->index(['club_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};
