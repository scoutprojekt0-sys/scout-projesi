<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scout_tip_id')->constrained('scout_tips')->cascadeOnDelete();
            $table->enum('reward_type', ['cash_bonus', 'wallet_credit', 'commission_share', 'gift', 'badge'])->default('badge');
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->enum('basis', ['trial', 'academy_acceptance', 'pro_contract', 'verified_transfer'])->default('trial');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('basis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_rewards');
    }
};
