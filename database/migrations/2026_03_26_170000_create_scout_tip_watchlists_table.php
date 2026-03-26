<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_tip_watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scout_tip_id')->constrained('scout_tips')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['manager_user_id', 'scout_tip_id']);
            $table->index(['manager_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_tip_watchlists');
    }
};
