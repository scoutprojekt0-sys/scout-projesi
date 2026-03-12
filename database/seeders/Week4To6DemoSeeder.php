<?php

namespace Database\Seeders;

use App\Models\ModerationQueue;
use App\Models\PlayerCareerTimeline;
use App\Models\PlayerMarketValue;
use App\Models\PlayerTransfer;
use App\Models\User;
use App\Models\UserContribution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Week4To6DemoSeeder extends Seeder
{
    public function run(): void
    {
        $clubA = User::firstOrCreate(
            ['email' => 'club-a@nextscout.pro'],
            [
                'name' => 'Istanbul Athletic',
                'password' => Hash::make('Password123'),
                'role' => 'team',
                'city' => 'Istanbul',
                'phone' => null,
            ]
        );

        $clubB = User::firstOrCreate(
            ['email' => 'club-b@nextscout.pro'],
            [
                'name' => 'Ankara United',
                'password' => Hash::make('Password123'),
                'role' => 'team',
                'city' => 'Ankara',
                'phone' => null,
            ]
        );

        $player = User::firstOrCreate(
            ['email' => 'player-demo@nextscout.pro'],
            [
                'name' => 'Demir Yilmaz',
                'password' => Hash::make('Password123'),
                'role' => 'player',
                'city' => 'Istanbul',
                'position' => 'FW',
                'age' => 24,
                'source_url' => 'https://nextscout.pro/demo-source',
                'has_source' => true,
                'verification_status' => 'verified',
                'confidence_score' => 0.85,
            ]
        );

        PlayerTransfer::firstOrCreate(
            [
                'player_id' => $player->id,
                'to_club_id' => $clubA->id,
                'transfer_date' => now()->subMonths(8)->toDateString(),
            ],
            [
                'from_club_id' => $clubB->id,
                'fee' => 450000.00,
                'currency' => 'EUR',
                'transfer_type' => 'permanent',
                'contract_until' => now()->addYears(2)->toDateString(),
                'season' => '2025-26',
                'window' => 'summer',
                'source_url' => 'https://nextscout.pro/demo-transfer',
                'confidence_score' => 0.82,
                'verification_status' => 'verified',
                'created_by' => $clubA->id,
            ]
        );

        PlayerCareerTimeline::firstOrCreate(
            [
                'player_id' => $player->id,
                'club_id' => $clubA->id,
                'season_start' => '2025-26',
            ],
            [
                'start_date' => now()->subMonths(8)->toDateString(),
                'season_end' => null,
                'is_current' => true,
                'position' => 'FW',
                'contract_type' => 'professional',
                'appearances' => 24,
                'goals' => 11,
                'assists' => 6,
                'minutes_played' => 1860,
                'yellow_cards' => 3,
                'red_cards' => 0,
                'source_url' => 'https://nextscout.pro/demo-career',
                'confidence_score' => 0.80,
                'verification_status' => 'verified',
                'created_by' => $clubA->id,
            ]
        );

        PlayerMarketValue::firstOrCreate(
            [
                'player_id' => $player->id,
                'valuation_date' => now()->subMonth()->toDateString(),
            ],
            [
                'value' => 950000.00,
                'currency' => 'EUR',
                'calculation_factors' => [
                    'age' => 24,
                    'position' => 'FW',
                    'recent_form' => 'high',
                ],
                'explanation' => 'Strong form, age advantage, and stable minutes played increased valuation.',
                'previous_value' => 850000.00,
                'value_change' => 100000.00,
                'value_change_percent' => 11.76,
                'peak_value' => 950000.00,
                'peak_value_date' => now()->subMonth()->toDateString(),
                'source_url' => 'https://nextscout.pro/demo-market-value',
                'confidence_score' => 0.78,
                'verification_status' => 'verified',
                'model_version' => 'v1.0',
                'created_by' => $clubA->id,
            ]
        );

        UserContribution::firstOrCreate(
            [
                'user_id' => $clubA->id,
                'model_type' => 'PlayerMarketValue',
                'description' => 'Updated valuation rationale with stronger recent-form evidence.',
            ],
            [
                'model_id' => 1,
                'contribution_type' => 'correction',
                'proposed_data' => ['value' => 980000],
                'current_data' => ['value' => 950000],
                'source_url' => 'https://nextscout.pro/demo-proof',
                'reasoning' => 'Recent match metrics justify revised value.',
                'status' => 'pending',
                'quality_score' => 0.72,
            ]
        );

        ModerationQueue::firstOrCreate(
            [
                'model_type' => 'UserContribution',
                'model_id' => 1,
                'status' => 'pending',
            ],
            [
                'priority' => 'medium',
                'reason' => 'new_entry',
                'proposed_changes' => ['value' => 980000],
                'current_values' => ['value' => 950000],
                'change_description' => 'Value update requires reviewer decision',
                'source_url' => 'https://nextscout.pro/demo-proof',
                'confidence_score' => 0.70,
                'submitted_by' => $clubA->id,
                'requires_dual_approval' => false,
            ]
        );
    }
}
