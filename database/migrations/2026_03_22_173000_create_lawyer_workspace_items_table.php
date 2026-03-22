<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lawyer_workspace_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->string('title', 180);
            $table->string('counterparty', 120)->nullable();
            $table->string('fee_label', 80)->nullable();
            $table->string('priority', 40)->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('deadline')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_workspace_items');
    }
};
