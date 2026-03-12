<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lawyers')) {
            return;
        }

        Schema::create('lawyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('license_number', 100)->unique();
            $table->string('specialization', 100);
            $table->text('bio')->nullable();
            $table->string('office_name', 150)->nullable();
            $table->string('office_address', 255)->nullable();
            $table->string('office_phone', 30)->nullable();
            $table->string('office_email', 120)->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('contract_fee', 10, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('license_status', ['valid', 'expired', 'suspended'])->default('valid');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['is_active', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyers');
    }
};
