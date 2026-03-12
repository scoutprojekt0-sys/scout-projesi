<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpportunityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Opportunity::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'team_user_id' => User::factory(),
            'title' => fake()->jobTitle() . ' Position Available',
            'position' => fake()->randomElement(['Forward', 'Midfielder', 'Defender', 'Goalkeeper']),
            'age_min' => fake()->numberBetween(18, 25),
            'age_max' => fake()->numberBetween(26, 35),
            'city' => fake()->city(),
            'details' => fake()->paragraphs(3, true),
            'status' => 'open',
        ];
    }

    /**
     * Indicate that the opportunity is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }
}
