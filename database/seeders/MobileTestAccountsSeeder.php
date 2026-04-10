<?php

namespace Database\Seeders;

use App\Models\PlayerProfile;
use App\Models\StaffProfile;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MobileTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password123!');

        $players = [
            [
                'name' => 'Arda Kaya',
                'email' => 'player1.mobile@nextscout.local',
                'city' => 'Istanbul',
                'position' => 'FW',
                'age' => 19,
                'country' => 'Turkey',
                'profile' => [
                    'birth_year' => 2007,
                    'position' => 'FW',
                    'dominant_foot' => 'right',
                    'height_cm' => 182,
                    'weight_kg' => 74,
                    'bio' => 'Mobile test striker profile.',
                    'current_team' => 'NextScout Test XI',
                ],
            ],
            [
                'name' => 'Ege Demir',
                'email' => 'player2.mobile@nextscout.local',
                'city' => 'Izmir',
                'position' => 'CM',
                'age' => 21,
                'country' => 'Turkey',
                'profile' => [
                    'birth_year' => 2005,
                    'position' => 'CM',
                    'dominant_foot' => 'left',
                    'height_cm' => 178,
                    'weight_kg' => 71,
                    'bio' => 'Mobile test midfielder profile.',
                    'current_team' => 'NextScout Test XI',
                ],
            ],
        ];

        $staff = [
            ['name' => 'Mert Scout', 'email' => 'scout1.mobile@nextscout.local', 'role' => 'scout', 'city' => 'Istanbul', 'organization' => 'Test Scout Group', 'experience_years' => 6],
            ['name' => 'Can Scout', 'email' => 'scout2.mobile@nextscout.local', 'role' => 'scout', 'city' => 'Bursa', 'organization' => 'Test Scout Group', 'experience_years' => 4],
            ['name' => 'Emre Coach', 'email' => 'coach1.mobile@nextscout.local', 'role' => 'coach', 'city' => 'Ankara', 'organization' => 'Test Coaching Staff', 'experience_years' => 9],
            ['name' => 'Baris Coach', 'email' => 'coach2.mobile@nextscout.local', 'role' => 'coach', 'city' => 'Antalya', 'organization' => 'Test Coaching Staff', 'experience_years' => 7],
            ['name' => 'Selim Manager', 'email' => 'manager1.mobile@nextscout.local', 'role' => 'manager', 'city' => 'Istanbul', 'organization' => 'Test Management Group', 'experience_years' => 8],
            ['name' => 'Kerem Manager', 'email' => 'manager2.mobile@nextscout.local', 'role' => 'manager', 'city' => 'Trabzon', 'organization' => 'Test Management Group', 'experience_years' => 5],
        ];

        $teams = [
            ['name' => 'NextScout Club One', 'email' => 'club1.mobile@nextscout.local', 'city' => 'Istanbul', 'team_name' => 'NextScout Club One', 'league_level' => 'U19 Elite'],
            ['name' => 'NextScout Club Two', 'email' => 'club2.mobile@nextscout.local', 'city' => 'Ankara', 'team_name' => 'NextScout Club Two', 'league_level' => 'Regional Pro'],
        ];

        foreach ($players as $playerData) {
            $user = User::updateOrCreate(
                ['email' => $playerData['email']],
                [
                    'name' => $playerData['name'],
                    'password' => $password,
                    'role' => 'player',
                    'city' => $playerData['city'],
                    'position' => $playerData['position'],
                    'age' => $playerData['age'],
                    'country' => $playerData['country'],
                    'is_public' => true,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'email_verification_token' => null,
                    'player_password_initialized' => true,
                ]
            );

            PlayerProfile::updateOrCreate(
                ['user_id' => $user->id],
                $playerData['profile']
            );
        }

        foreach ($staff as $staffData) {
            $user = User::updateOrCreate(
                ['email' => $staffData['email']],
                [
                    'name' => $staffData['name'],
                    'password' => $password,
                    'role' => $staffData['role'],
                    'city' => $staffData['city'],
                    'is_public' => true,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'email_verification_token' => null,
                ]
            );

            StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'role_type' => $staffData['role'],
                    'organization' => $staffData['organization'],
                    'experience_years' => $staffData['experience_years'],
                    'bio' => 'Mobile test staff account.',
                ]
            );
        }

        foreach ($teams as $teamData) {
            $user = User::updateOrCreate(
                ['email' => $teamData['email']],
                [
                    'name' => $teamData['name'],
                    'password' => $password,
                    'role' => 'team',
                    'city' => $teamData['city'],
                    'is_public' => true,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'email_verification_token' => null,
                ]
            );

            TeamProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'team_name' => $teamData['team_name'],
                    'league_level' => $teamData['league_level'],
                    'city' => $teamData['city'],
                    'founded_year' => 2012,
                    'needs_text' => 'Mobile test club needs a left-footed winger and athletic centre-back.',
                ]
            );
        }
    }
}
