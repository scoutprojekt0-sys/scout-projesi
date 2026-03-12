<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('video_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->enum('platform', ['youtube', 'vimeo', 'custom']);
            $table->string('platform_video_id', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->date('match_date')->nullable();
            $table->json('tags')->nullable(); // ['goal', 'assist', 'defense', 'pass']
            $table->json('metadata')->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_clips');
    }
};
