<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends a text message through Meta's official WhatsApp Cloud API, using
 * the instance owner's OWN Meta credentials (phone number ID + access
 * token). Only used as the optional fallback in MessageSender, when the
 * linked device (Baileys, via the worker) couldn't send.
 *
 * Meta only accepts free-form text to people who messaged that business
 * number in the last 24 hours; anything else is rejected (Meta wants a
 * pre-approved template then) and simply ends up as fallback "failed".
 */
class CloudApiClient
{
    /**
     * Returns Meta's message id ("wamid...."). Throws on any failure, with
     * Meta's own error text when it gave one. The access token is only
     * ever sent in the Authorization header — never logged.
     */
    public function sendText(WhatsappSession $session, string $to, string $body): string
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp_cloud.graph_version'),
            rawurlencode((string) $session->cloud_phone_number_id),
        );

        try {
            $response = Http::withToken((string) $session->cloud_access_token)
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $body],
                ])
                ->throw();
        } catch (RequestException $e) {
            $error = $e->response->json('error.message') ?? 'HTTP '.$e->response->status();

            throw new RuntimeException('Cloud API: '.$error, previous: $e);
        }

        return $response->json('messages.0.id')
            ?? throw new RuntimeException('Cloud API: no message id in the response.');
    }
}
