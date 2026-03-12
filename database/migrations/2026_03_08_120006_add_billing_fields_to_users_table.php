<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('remember_token');
            $table->string('paypal_customer_id')->nullable()->after('stripe_customer_id');
            $table->string('subscription_status')->default('free')->after('paypal_customer_id');
            $table->boolean('is_public')->default(false)->after('subscription_status');
            $table->string('position')->nullable()->after('is_public');
            $table->string('country')->nullable()->after('position');
            $table->integer('age')->nullable()->after('country');
            $table->string('photo_url')->nullable()->after('age');
            $table->integer('views_count')->default(0)->after('photo_url');
            $table->decimal('rating', 3, 2)->nullable()->after('views_count');
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
