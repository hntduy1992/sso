<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone_number' => fake()->numerify('0#########'),
            'contact_email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'avatar_path' => null,
            'bio' => fake()->optional(0.4)->sentence(),
        ];
    }

    /**
     * Profile without optional fields (minimal state for new users).
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => null,
            'gender' => null,
            'phone_number' => null,
            'contact_email' => null,
            'address' => null,
            'bio' => null,
        ]);
    }
}
