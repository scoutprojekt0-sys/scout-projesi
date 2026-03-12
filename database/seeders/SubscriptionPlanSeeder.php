<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subscription_plans')->insert([
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Basic features for getting started',
                'price' => 0,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => json_encode(['Basic profile', 'Limited search', 'View opportunities']),
                'active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Scout Pro',
                'slug' => 'scout-pro',
                'description' => 'Advanced scouting features',
                'price' => 29.99,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => json_encode(['Unlimited search', 'Advanced filters', 'Contact players', 'Analytics']),
                'active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Manager Pro',
                'slug' => 'manager-pro',
                'description' => 'Full manager toolkit',
                'price' => 49.99,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => json_encode(['Everything in Scout Pro', 'Post opportunities', 'Team management', 'Priority support']),
                'active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Club Premium',
                'slug' => 'club-premium',
                'description' => 'Enterprise solution for clubs',
                'price' => 199.99,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'features' => json_encode(['Everything in Manager Pro', 'Multi-user access', 'Custom branding', 'Dedicated account manager', 'API access']),
                'active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
