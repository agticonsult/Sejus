<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebRtcWebhookControllerTest extends TestCase
{
    protected string $secret = 'sejus_webrtc_webhook_secret_2026';

    public function test_rejects_webhook_without_signature_header(): void
    {
        $response = $this->postJson('/api/webhooks/webrtc', [
            'event' => 'session.started',
            'room_id' => 'room-test-1',
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $response = $this->postJson('/api/webhooks/webrtc', [
            'event' => 'session.started',
            'room_id' => 'room-test-1',
        ], [
            'X-Signature' => 'sha256=invalid_signature_hash',
        ]);

        $response->assertStatus(401);
    }

    public function test_accepts_webhook_with_valid_hmac_signature(): void
    {
        $payload = json_encode([
            'event' => 'session.started',
            'room_id' => 'room-test-vitoria-101',
            'data' => [
                'room_code' => 'room-test-vitoria-101',
                'started_at' => date('c'),
            ],
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/webrtc',
            [],
            [],
            [],
            [
                'HTTP_X-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
    }
}
