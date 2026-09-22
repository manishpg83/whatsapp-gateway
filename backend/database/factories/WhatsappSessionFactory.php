<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappSession>
 */
class WhatsappSessionFactory extends Factory
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
            'name' => fake()->words(2, true),
            'status' => 'connecting',
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'connected',
            'phone_number' => '91'.fake()->numerify('##########'),
            'connected_at' => now(),
        ]);
    }
}
