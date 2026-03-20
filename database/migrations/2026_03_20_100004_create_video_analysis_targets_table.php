<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_analysis_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_analysis_id')->constrained('video_analyses')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label', 120)->nullable();
            $table->string('jersey_number', 20)->nullable();
            $table->json('reference_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_analysis_targets');
    }
};
