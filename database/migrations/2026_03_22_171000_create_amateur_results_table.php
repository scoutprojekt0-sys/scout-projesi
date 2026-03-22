<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amateur_results', function (Blueprint $table) {
            $table->id();
            $table->string('league', 120);
            $table->string('season', 30);
            $table->string('country', 80)->default('Turkiye');
            $table->string('sport', 40)->default('futbol');
            $table->string('home_team', 120);
            $table->string('away_team', 120);
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            $table->string('status', 20)->default('pending');
            $table->string('source', 40)->default('admin');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amateur_results');
    }
};
