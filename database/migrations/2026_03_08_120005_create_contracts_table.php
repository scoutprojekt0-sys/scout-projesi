<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('club_id')->constrained('users')->onDelete('cascade');
            $table->string('contract_type'); // permanent, loan, trial
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['active', 'terminated', 'expired'])->default('active');
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'status']);
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
