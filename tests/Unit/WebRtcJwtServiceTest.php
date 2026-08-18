<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\WebRtcJwtService;
use App\Models\User;

class WebRtcJwtServiceTest extends TestCase
{
    protected WebRtcJwtService $service;
    protected string $secret = 'test_webrtc_jwt_secret_2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebRtcJwtService($this->secret, 3600);
    }

    public function test_encodes_and_verifies_valid_jwt(): void
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => '101',
            'role' => 'tecnico',
            'room_id' => 'room-vitoria-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $jwt = $this->service->encodeJwt($header, $payload, $this->secret);
        $this->assertNotEmpty($jwt);
        $this->assertCount(3, explode('.', $jwt));

        $result = $this->service->verifyJwt($jwt);
        $this->assertTrue($result['valid']);
        $this->assertEquals('101', $result['payload']['sub']);
        $this->assertEquals('tecnico', $result['payload']['role']);
    }

    public function test_rejects_jwt_with_invalid_signature(): void
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => '101',
            'role' => 'egresso',
            'exp' => time() + 3600,
        ];

        $jwt = $this->service->encodeJwt($header, $payload, 'wrong_secret_key');
        $result = $this->service->verifyJwt($jwt);

        $this->assertFalse($result['valid']);
        $this->assertEquals('INVALID_SIGNATURE', $result['error']);
    }

    public function test_rejects_expired_jwt(): void
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => '101',
            'role' => 'tecnico',
            'exp' => time() - 100, // Expired in the past
        ];

        $jwt = $this->service->encodeJwt($header, $payload, $this->secret);
        $result = $this->service->verifyJwt($jwt);

        $this->assertFalse($result['valid']);
        $this->assertEquals('TOKEN_EXPIRED', $result['error']);
    }

    public function test_rejects_jwt_not_yet_valid(): void
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub' => '101',
            'role' => 'tecnico',
            'nbf' => time() + 1000, // Future valid time
            'exp' => time() + 3600,
        ];

        $jwt = $this->service->encodeJwt($header, $payload, $this->secret);
        $result = $this->service->verifyJwt($jwt);

        $this->assertFalse($result['valid']);
        $this->assertEquals('TOKEN_NOT_YET_VALID', $result['error']);
    }

    public function test_returns_ice_servers_and_websocket_url(): void
    {
        $iceServers = $this->service->getIceServers();
        $this->assertIsArray($iceServers);
        $this->assertGreaterThanOrEqual(2, count($iceServers));

        $wsUrl = $this->service->getWebSocketUrl('sala-vitoria-101');
        $this->assertStringContainsString('sala-vitoria-101', $wsUrl);
        $this->assertTrue(str_starts_with($wsUrl, 'ws://') || str_starts_with($wsUrl, 'wss://'));
    }
}
