<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
            $table->string('paypal_customer_id')->nullable();
            $table->string('subscription_status')->default('free');
            $table->boolean('is_public')->default(false);
            $table->string('position')->nullable();
            $table->string('country')->nullable();
            $table->integer('age')->nullable();
            $table->string('photo_url')->nullable();
            $table->integer('views_count')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'paypal_customer_id',
                'subscription_status',
                'is_public',
                'position',
                'country',
                'age',
                'photo_url',
                'views_count',
                'rating',
            ]);
        });
    }
};
