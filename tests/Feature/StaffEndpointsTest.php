<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_staff_can_persist_extended_profile_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'scout',
            'name' => 'Scout One',
            'city' => 'Istanbul',
        ]);

        DB::table('staff_profiles')->insert([
            'user_id' => $user->id,
            'role_type' => 'scout',
            'branch' => 'Futbol',
            'organization' => null,
            'experience_years' => 4,
            'bio' => 'Initial bio',
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user, $user->tokenAbilities());

        $this->putJson('/api/staff/'.$user->id, [
            'name' => 'Scout Updated',
            'city' => 'Izmir',
            'country' => 'Turkiye',
            'phone' => '+90 555 111 11 11',
            'role_type' => 'scout',
            'branch' => 'Basketbol',
            'experience_years' => 7,
            'bio' => 'Updated bio',
            'focus' => 'Youth prospects and transition profiles',
            'coverage' => 'Izmir, Manisa, Aydin',
            'scouting_notes' => 'Prefers live match evaluations',
            'profile_photo_url' => 'https://example.com/scout.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.focus', 'Youth prospects and transition profiles')
            ->assertJsonPath('data.coverage', 'Izmir, Manisa, Aydin')
            ->assertJsonPath('data.scouting_notes', 'Prefers live match evaluations');

        $this->getJson('/api/staff/'.$user->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Scout Updated')
            ->assertJsonPath('data.focus', 'Youth prospects and transition profiles')
            ->assertJsonPath('data.coverage', 'Izmir, Manisa, Aydin')
            ->assertJsonPath('data.scouting_notes', 'Prefers live match evaluations');

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $user->id,
            'focus' => 'Youth prospects and transition profiles',
            'coverage' => 'Izmir, Manisa, Aydin',
            'scouting_notes' => 'Prefers live match evaluations',
        ]);
    }
}
