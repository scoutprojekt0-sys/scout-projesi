<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('from_club_id')->nullable();
            $table->unsignedBigInteger('to_club_id');

            // Transfer details
            $table->decimal('fee', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('transfer_date');
            $table->enum('transfer_type', ['permanent', 'loan', 'free', 'end_of_loan', 'unknown'])->default('permanent');
            $table->date('contract_until')->nullable();

            // Season context
            $table->string('season', 10);
            $table->enum('window', ['summer', 'winter', 'special'])->default('summer');

            // Data quality
            $table->string('source_url')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.5);
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('player_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('from_club_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('to_club_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['player_id', 'transfer_date']);
            $table->index(['season', 'window']);
            $table->index('verification_status');
            $table->index('transfer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_transfers');
    }
};
