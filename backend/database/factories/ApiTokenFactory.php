<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\WhatsappSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainText = Str::random(64);

        return [
            'whatsapp_session_id' => WhatsappSession::factory(),
            'name' => fake()->words(2, true),
            'token_hash' => hash('sha256', $plainText),
            'token_prefix' => substr($plainText, 0, 8),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
