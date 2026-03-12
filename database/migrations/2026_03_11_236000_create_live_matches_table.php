<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_matches')) {
            return;
        }

        Schema::create('live_matches', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('league', 120)->nullable();
            $table->string('home_team', 120)->nullable();
            $table->string('away_team', 120)->nullable();
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->timestamp('match_date')->nullable();
            $table->boolean('is_live')->default(true);
            $table->boolean('is_finished')->default(false);
            $table->string('round', 255)->nullable();
            $table->timestamps();

            $table->index(['is_live', 'is_finished']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_matches');
    }
};
