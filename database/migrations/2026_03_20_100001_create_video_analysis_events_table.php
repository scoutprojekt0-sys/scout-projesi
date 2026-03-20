<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_analysis_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_analysis_id')->constrained('video_analyses')->cascadeOnDelete();
            $table->foreignId('target_player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->unsignedInteger('start_second');
            $table->unsignedInteger('end_second');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['video_analysis_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_analysis_events');
    }
};
