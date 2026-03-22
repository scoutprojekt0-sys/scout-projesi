<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('club_name', 120);
            $table->text('notes')->nullable();
            $table->string('video_url', 2000)->nullable();
            $table->json('images')->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_promos');
    }
};
