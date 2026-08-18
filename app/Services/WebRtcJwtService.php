<?php

namespace App\Services;

use App\Models\User;

class WebRtcJwtService
{
    protected string $secretKey;
    protected string $issuer;
    protected string $audience;
    protected int $ttl;

    public function __construct(?string $secretKey = null, int $ttl = 3600)
    {
        if ($secretKey !== null) {
            $this->secretKey = $secretKey;
        } elseif (function_exists('config') && config('services.webrtc.jwt_secret')) {
            $this->secretKey = (string) config('services.webrtc.jwt_secret');
        } elseif (function_exists('env') && env('WEBRTC_JWT_SECRET')) {
            $this->secretKey = (string) env('WEBRTC_JWT_SECRET');
        } else {
            $this->secretKey = (string) (getenv('WEBRTC_JWT_SECRET') ?: 'sejus_jwt_shared_secret_2026');
        }

        $this->issuer = 'conecta-egresso-laravel';
        $this->audience = 'conecta-egresso-webrtc';
        $this->ttl = $ttl;
    }

    /**
     * Generate signed RFC 7519 HS256 JWT for WebRTC signaling session.
     */
    public function generateRoomToken(
        User $user,
        string $roomId,
        ?string $role = null,
        ?int $prontuarioId = null,
        ?int $unitId = null,
        ?string $roomCode = null
    ): array {
        $now = time();
        $expiresAt = $now + $this->ttl;
        $resolvedRole = $role ?? $user->perfil?->slug ?? 'egresso';
        $resolvedRoomCode = $roomCode ?? $roomId;

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'cpf_masked' => $user->cpf ? app(LgpdSecurityService::class)->maskCpf($user->cpf) : null,
            'role' => $resolvedRole,
            'room_id' => $roomId,
            'room_code' => $resolvedRoomCode,
            'prontuario_id' => $prontuarioId,
            'unit_id' => $unitId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $jwt = $this->encodeJwt($header, $payload, $this->secretKey);

        return [
            'token' => $jwt,
            'room_id' => $roomId,
            'room_code' => $resolvedRoomCode,
            'role' => $resolvedRole,
            'expires_in' => $this->ttl,
            'expires_at' => date('c', $expiresAt),
            'ice_servers' => $this->getIceServers(),
            'ws_url' => $this->getWebSocketUrl($roomId),
        ];
    }

    /**
     * Validate and decode incoming JWT token.
     */
    public function verifyJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'MALFORMED_JWT_STRUCTURE'];
        }

        [$b64Header, $b64Payload, $b64Signature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $this->secretKey, true)
        );

        if (!hash_equals($expectedSignature, $b64Signature)) {
            return ['valid' => false, 'error' => 'INVALID_SIGNATURE'];
        }

        $payloadJson = $this->base64UrlDecode($b64Payload);
        $payload = json_decode($payloadJson, true);

        if (!$payload || !is_array($payload)) {
            return ['valid' => false, 'error' => 'INVALID_PAYLOAD_JSON'];
        }

        $now = time();

        if (isset($payload['exp']) && $now > $payload['exp']) {
            return ['valid' => false, 'error' => 'TOKEN_EXPIRED', 'payload' => $payload];
        }

        if (isset($payload['nbf']) && $now < $payload['nbf']) {
            return ['valid' => false, 'error' => 'TOKEN_NOT_YET_VALID', 'payload' => $payload];
        }

        return ['valid' => true, 'payload' => $payload];
    }

    /**
     * Encode header and payload into a signed JWT string.
     */
    public function encodeJwt(array $header, array $payload, string $secret): string
    {
        $b64Header = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $b64Payload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $secret, true);
        $b64Signature = $this->base64UrlEncode($signature);

        return "{$b64Header}.{$b64Payload}.{$b64Signature}";
    }

    /**
     * Base64URL encode string without trailing '='.
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode string.
     */
    public function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT);
        return (string) base64_decode($padded);
    }

    /**
     * Return Coturn STUN/TURN ICE servers configuration.
     */
    public function getIceServers(): array
    {
        $coturnHost = function_exists('config') && config('services.webrtc.coturn.host')
            ? (string) config('services.webrtc.coturn.host')
            : (string) (getenv('COTURN_HOST') ?: 'turn.conectaegresso.es.gov.br');

        $coturnPort = function_exists('config') && config('services.webrtc.coturn.port')
            ? (int) config('services.webrtc.coturn.port')
            : (int) (getenv('COTURN_PORT') ?: 3478);

        return [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => "stun:{$coturnHost}:{$coturnPort}"],
            [
                'urls' => "turn:{$coturnHost}:{$coturnPort}?transport=udp",
                'username' => 'conecta_user',
                'credential' => 'conecta_password',
            ],
            [
                'urls' => "turn:{$coturnHost}:{$coturnPort}?transport=tcp",
                'username' => 'conecta_user',
                'credential' => 'conecta_password',
            ],
        ];
    }

    /**
     * Generate WebSocket signaling URL for Python microservice.
     */
    public function getWebSocketUrl(string $roomId): string
    {
        $baseUrl = function_exists('config') && config('services.webrtc.service_url')
            ? (string) config('services.webrtc.service_url')
            : (string) (getenv('WEBRTC_SERVICE_URL') ?: 'http://localhost:8001');

        $wsScheme = str_starts_with($baseUrl, 'https') ? 'wss' : 'ws';
        $hostPort = preg_replace('#^https?://#', '', $baseUrl);

        return "{$wsScheme}://{$hostPort}/ws/signaling/{$roomId}";
    }
}
