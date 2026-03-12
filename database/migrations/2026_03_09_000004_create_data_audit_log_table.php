<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_audit_log', function (Blueprint $table) {
            $table->id();

            // What changed
            $table->string('model_type'); // Player, Team, Transfer, etc.
            $table->unsignedBigInteger('model_id');
            $table->enum('action', ['created', 'updated', 'deleted', 'verified', 'rejected'])->default('updated');

            // Changes detail
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();

            // Who and when
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Context
            $table->text('reason')->nullable();
            $table->string('source', 100)->nullable(); // web, api, import, system

            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_audit_log');
    }
};
