<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\WhatsappSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_session_id' => WhatsappSession::factory(),
            'direction' => 'outgoing',
            'to_number' => '91'.fake()->numerify('##########'),
            'body' => fake()->sentence(),
            'status' => 'sent',
            'whatsapp_message_id' => fake()->uuid(),
        ];
    }
}
