<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_market_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');

            // Market value
            $table->decimal('value', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('valuation_date');

            // Value calculation factors
            $table->json('calculation_factors')->nullable(); // age, performance, league, etc.
            $table->text('explanation')->nullable(); // Why this value?

            // Comparison
            $table->decimal('previous_value', 12, 2)->nullable();
            $table->decimal('value_change', 12, 2)->nullable();
            $table->decimal('value_change_percent', 5, 2)->nullable();

            // Peak value tracking
            $table->decimal('peak_value', 12, 2)->nullable();
            $table->date('peak_value_date')->nullable();

            // Data quality
            $table->string('source_url')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.5);
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');

            // Model version
            $table->string('model_version', 20)->default('v1.0');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('player_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['player_id', 'valuation_date']);
            $table->index('valuation_date');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_market_values');
    }
};
