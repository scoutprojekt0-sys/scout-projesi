<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_timeline', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('club_id');

            // Timeline period
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('season_start', 10);
            $table->string('season_end', 10)->nullable();
            $table->boolean('is_current')->default(false);

            // Position & role
            $table->string('position', 50)->nullable();
            $table->enum('contract_type', ['professional', 'youth', 'amateur', 'loan'])->default('professional');

            // Statistics
            $table->integer('appearances')->default(0);
            $table->integer('goals')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('minutes_played')->default(0);
            $table->integer('yellow_cards')->default(0);
            $table->integer('red_cards')->default(0);

            // Data quality
            $table->string('source_url')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.5);
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('player_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('club_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['player_id', 'start_date']);
            $table->index('is_current');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_timeline');
    }
};
