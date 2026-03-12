<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_searches')) {
            Schema::create('player_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
                $table->string('position', 40)->nullable();
                $table->string('city', 80)->nullable();
                $table->unsignedTinyInteger('min_age')->nullable();
                $table->unsignedTinyInteger('max_age')->nullable();
                $table->unsignedSmallInteger('min_height_cm')->nullable();
                $table->unsignedSmallInteger('max_height_cm')->nullable();
                $table->decimal('min_rating', 5, 2)->nullable();
                $table->boolean('save_search')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('player_search_results')) {
            Schema::create('player_search_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('search_id')->constrained('player_searches')->cascadeOnDelete();
                $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('match_score', 5, 2)->default(0);
                $table->json('match_details')->nullable();
                $table->timestamps();

                $table->unique(['search_id', 'player_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_search_results');
        Schema::dropIfExists('player_searches');
    }
};
