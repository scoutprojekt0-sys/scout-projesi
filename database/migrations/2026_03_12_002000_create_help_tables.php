<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_categories')) {
            Schema::create('help_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable();
                $table->unsignedSmallInteger('order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('help_articles')) {
            Schema::create('help_articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('help_categories')->cascadeOnDelete();
                $table->string('title', 200);
                $table->string('slug', 200)->unique();
                $table->longText('content');
                $table->text('meta_description')->nullable();
                $table->json('keywords')->nullable();
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('helpful_count')->default(0);
                $table->unsignedInteger('unhelpful_count')->default(0);
                $table->boolean('is_published')->default(true);
                $table->unsignedSmallInteger('order')->default(0);
                $table->timestamps();

                $table->index(['category_id', 'is_published']);
            });
        }

        if (!Schema::hasTable('faq')) {
            Schema::create('faq', function (Blueprint $table) {
                $table->id();
                $table->string('question', 300);
                $table->longText('answer');
                $table->enum('user_type', ['player', 'manager', 'coach', 'scout', 'team', 'all'])->default('all');
                $table->enum('topic', ['account', 'profile', 'messaging', 'search', 'contracts', 'payments', 'technical', 'other'])->default('other');
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('helpful_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('order')->default(0);
                $table->timestamps();

                $table->index(['user_type', 'topic', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq');
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
    }
};
