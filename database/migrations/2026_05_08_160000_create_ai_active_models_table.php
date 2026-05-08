<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_active_models', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 40)->unique();
            $table->string('model_version', 120);
            $table->string('model_path', 500)->nullable();
            $table->foreignId('ai_training_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_model_rollouts', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 40);
            $table->string('from_model_version', 120)->nullable();
            $table->string('to_model_version', 120);
            $table->string('action', 40)->default('publish');
            $table->string('model_path', 500)->nullable();
            $table->foreignId('ai_training_run_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('rolled_out_at')->nullable();
            $table->timestamps();

            $table->index(['sport', 'rolled_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_rollouts');
        Schema::dropIfExists('ai_active_models');
    }
};
