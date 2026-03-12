<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('featured_content')) {
            return;
        }

        Schema::create('featured_content', function (Blueprint $table) {
            $table->id();
            $table->string('featurable_type', 50);
            $table->unsignedBigInteger('featurable_id');
            $table->string('section', 30)->default('homepage');
            $table->unsignedInteger('priority')->default(0);
            $table->string('badge_text', 50)->nullable();
            $table->string('badge_color', 20)->nullable();
            $table->timestamp('featured_from')->nullable();
            $table->timestamp('featured_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section', 'is_active', 'priority']);
            $table->unique(['featurable_type', 'featurable_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_content');
    }
};
