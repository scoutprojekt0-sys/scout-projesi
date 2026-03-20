<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Test kullanıcıları
        User::query()->firstOrCreate(
            ['email' => 'player@test.com'],
            [
                'name'       => 'Test Oyuncu',
                'password'   => Hash::make('Password123!'),
                'role'       => 'player',
                'city'       => 'Istanbul',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'team@test.com'],
            [
                'name'       => 'Test Takım',
                'password'   => Hash::make('Password123!'),
                'role'       => 'team',
                'city'       => 'Ankara',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'scout@test.com'],
            [
                'name'       => 'Test Scout',
                'password'   => Hash::make('Password123!'),
                'role'       => 'scout',
                'city'       => 'Izmir',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            SubscriptionPlanSeeder::class,
            DemoDataSeeder::class,
            Week4To6DemoSeeder::class,
            AiDiscoveryDemoSeeder::class,
        ]);
    }
}
