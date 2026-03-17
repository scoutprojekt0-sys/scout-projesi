<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boost_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('TRY');
            $table->unsignedInteger('duration_days')->default(7);
            $table->unsignedInteger('discover_score')->default(1);
            $table->string('provider_product_code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boost_packages');
    }
};
