<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['player', 'scout', 'manager', 'coach', 'team']),
            'city' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->randomElement(['Forward', 'Midfielder', 'Defender', 'Goalkeeper']),
            'age' => fake()->numberBetween(18, 35),
            'rating' => fake()->randomFloat(2, 5.0, 9.5),
            'views_count' => fake()->numberBetween(0, 1000),
            'subscription_status' => 'free',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a player.
     */
    public function player(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'player',
        ]);
    }

    /**
     * Indicate that the user is a club.
     */
    public function club(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'team',
            'position' => null,
            'age' => null,
        ]);
    }
}
