<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_player_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name', 120);
            $table->decimal('amount_eur', 12, 2);
            $table->string('status', 40)->default('sent');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_offers');
    }
};
