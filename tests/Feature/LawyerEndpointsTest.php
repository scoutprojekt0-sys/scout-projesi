<?php

namespace Tests\Feature;

use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LawyerEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_lawyers(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'name' => 'Lawyer User',
            'city' => 'Istanbul',
        ]);

        Lawyer::query()->create([
            'user_id' => $user->id,
            'license_number' => 'TR-100',
            'specialization' => 'Sports law',
            'years_experience' => 8,
            'is_verified' => true,
            'is_active' => true,
            'license_status' => 'valid',
        ]);

        $this->getJson('/api/lawyers?verified_only=1')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user.name', 'Lawyer User')
            ->assertJsonMissingPath('data.0.user.email');
    }

    public function test_authenticated_user_can_register_and_update_lawyer_profile(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        Sanctum::actingAs($user, $user->tokenAbilities());

        $register = $this->postJson('/api/lawyers/register', [
            'license_number' => 'TR-200',
            'specialization' => 'Contract law',
            'years_experience' => 5,
            'hourly_rate' => 120,
        ]);

        $register
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user_id', $user->id);

        $lawyerId = (int) $register->json('data.id');

        $this->getJson('/api/lawyers/'.$lawyerId)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.specialization', 'Contract law')
            ->assertJsonMissingPath('data.license_number')
            ->assertJsonMissingPath('data.user.email');

        $this->putJson('/api/lawyers/'.$lawyerId, [
            'bio' => 'Sports contracts specialist',
            'office_name' => 'NextScout Legal',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.office_name', 'NextScout Legal');
    }

    public function test_user_cannot_update_another_lawyer_profile(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);

        $lawyer = Lawyer::query()->create([
            'user_id' => $owner->id,
            'license_number' => 'TR-300',
            'specialization' => 'Sports law',
            'is_active' => true,
            'license_status' => 'valid',
        ]);

        Sanctum::actingAs($other, $other->tokenAbilities());

        $this->putJson('/api/lawyers/'.$lawyer->id, [
            'office_name' => 'Forbidden',
        ])
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }
}
