<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('boost_packages')->upsert([
            [
                'slug' => 'daily-boost',
                'name' => 'Gunluk Vitrin',
                'description' => '24 saat kesfet alaninda one cik.',
                'price' => 149.00,
                'currency' => 'TRY',
                'duration_days' => 1,
                'discover_score' => 10,
                'provider_product_code' => 'boost_daily',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'weekly-boost',
                'name' => 'Haftalik Vitrin',
                'description' => '7 gun kesfet alaninda daha gorunur ol.',
                'price' => 499.00,
                'currency' => 'TRY',
                'duration_days' => 7,
                'discover_score' => 25,
                'provider_product_code' => 'boost_weekly',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'monthly-boost',
                'name' => 'Aylik Vitrin',
                'description' => '30 gun kesfet alaninda kalici gorunurluk kazan.',
                'price' => 1499.00,
                'currency' => 'TRY',
                'duration_days' => 30,
                'discover_score' => 60,
                'provider_product_code' => 'boost_monthly',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'description', 'price', 'currency', 'duration_days', 'discover_score', 'provider_product_code', 'active', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('boost_packages')
            ->whereIn('slug', ['daily-boost', 'weekly-boost', 'monthly-boost'])
            ->delete();
    }
};
