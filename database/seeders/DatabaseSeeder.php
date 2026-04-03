<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Test kullanıcıları
        User::query()->updateOrCreate(
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

        User::query()->updateOrCreate(
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

        User::query()->updateOrCreate(
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

        $debugScout = User::query()->updateOrCreate(
            ['email' => 'scout.debug@test.com'],
            [
                'name'       => 'Debug Scout Mobile',
                'password'   => Hash::make('Password123!'),
                'role'       => 'scout',
                'city'       => 'Istanbul',
                'phone'      => '+90 555 010 2026',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        DB::table('staff_profiles')->updateOrInsert(
            ['user_id' => $debugScout->id],
            [
                'role_type' => 'scout',
                'branch' => 'Futbol',
                'organization' => 'Scout Mobile Debug Unit',
                'experience_years' => 8,
                'bio' => 'Mobil test akislari icin hazirlanan debug scout profili. Genc oyuncu takibi, saha raporu ve tekrar izleme odaklidir.',
                'updated_at' => now(),
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
