<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_player_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name', 120);
            $table->string('video_url', 2000);
            $table->unsignedSmallInteger('minute_mark')->default(0);
            $table->unsignedTinyInteger('second_mark')->default(0);
            $table->string('stamp', 8);
            $table->text('body');
            $table->timestamps();

            $table->index(['coach_user_id', 'created_at']);
            $table->index(['player_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_player_clips');
    }
};
