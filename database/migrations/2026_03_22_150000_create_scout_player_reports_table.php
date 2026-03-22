<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_player_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scout_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name', 120);
            $table->string('position', 120);
            $table->unsignedSmallInteger('age')->nullable();
            $table->decimal('rating', 3, 1);
            $table->string('status', 40)->default('review');
            $table->string('scout_name', 120);
            $table->string('club', 120)->nullable();
            $table->date('watched_at')->nullable();
            $table->string('potential', 120)->nullable();
            $table->text('summary')->nullable();
            $table->json('strengths')->nullable();
            $table->json('risks')->nullable();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_player_reports');
    }
};
