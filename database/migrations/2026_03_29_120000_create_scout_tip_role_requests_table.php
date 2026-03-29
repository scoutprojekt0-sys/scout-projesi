<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_tip_role_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scout_tip_id')->constrained('scout_tips')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_type', 20);
            $table->string('status', 30)->default('requested');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scout_tip_id', 'user_id', 'role_type'], 'scout_tip_role_requests_unique');
            $table->index(['scout_tip_id', 'role_type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_tip_role_requests');
    }
};
