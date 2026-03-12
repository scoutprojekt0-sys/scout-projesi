<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Opportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo players
        $players = [];
        for ($i = 1; $i <= 10; $i++) {
            $players[] = User::create([
                'name' => "Demo Player {$i}",
                'email' => "player{$i}@demo.com",
                'password' => bcrypt('password'),
                'role' => 'player',
                'city' => ['Istanbul', 'Ankara', 'Izmir', 'Bursa'][rand(0, 3)],
                'position' => ['Forward', 'Midfielder', 'Defender', 'Goalkeeper'][rand(0, 3)],
                'age' => rand(18, 30),
                'rating' => rand(60, 95) / 10,
                'views_count' => rand(0, 1000),
            ]);
        }

        // Create demo teams
        $teams = [];
        for ($i = 1; $i <= 3; $i++) {
            $teams[] = User::create([
                'name' => "Demo Club {$i}",
                'email' => "club{$i}@demo.com",
                'password' => bcrypt('password'),
                'role' => 'team',
                'city' => 'Istanbul',
            ]);
        }

        // Create demo opportunities
        foreach ($teams as $team) {
            Opportunity::create([
                'team_user_id' => $team->id,
                'title' => "Midfielder Position Available",
                'position' => 'Midfielder',
                'age_min' => 20,
                'age_max' => 28,
                'city' => 'Istanbul',
                'details' => "Looking for a talented midfielder to join our squad",
                'status' => 'open',
            ]);
        }

        // Create demo contracts
        foreach (array_slice($players, 0, 5) as $index => $player) {
            DB::table('contracts')->insert([
                'player_id' => $player->id,
                'club_id' => $teams[rand(0, count($teams) - 1)]->id,
                'contract_type' => 'permanent',
                'start_date' => now()->subMonths(rand(1, 12)),
                'end_date' => now()->addMonths(rand(12, 36)),
                'salary' => rand(50000, 500000),
                'currency' => 'USD',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create demo notifications
        foreach ($players as $player) {
            DB::table('notifications')->insert([
                'user_id' => $player->id,
                'type' => 'opportunity',
                'payload' => json_encode(['opportunity_id' => 1]),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
