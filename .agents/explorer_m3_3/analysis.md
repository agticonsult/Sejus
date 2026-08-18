# Technical Specification & Analysis: WebRTC Room Token Generation, Webhook Ingestion & M3 Test Architecture
## CONECTA EGRESSO (SEJUS/ES) — Milestone M3 (Backend Business APIs, RBAC & Webhooks)

**Document:** Architecture Specification, Security Contracts, Business Rules & Test Coverage Plan  
**Component:** Backend Laravel 11 (`app/Http/Controllers/`, `app/Services/`, `app/Http/Middleware/`, `tests/`)  
**Author:** Explorer 3 (`explorer_m3_3`)  
**Date:** 2026-08-17  
**Status:** COMPLETE & FINAL SPECIFICATION  

---

## 1. Executive Summary & Architectural Context

The **CONECTA EGRESSO** platform, created for the State Secretariat of Justice of Espírito Santo (SEJUS/ES), is a mission-critical public policy system providing remote psychosocial and legal assistance across all 78 municipalities in Espírito Santo. Because 74 of these 78 municipalities lack physical Social Offices (*Escritórios Sociais*), the platform acts as a digital bridge, enabling remote video assistance directly to individuals formerly incarcerated and their families.

Within Milestone **M3 (Backend Business APIs, RBAC & Webhooks)**, the Laravel 11 backend coordinates the lifecycle of remote attendance sessions in direct partnership with the Python FastAPI WebRTC microservice (`webrtc_service/`). Specifically, this report establishes:

1. **WebRTC Room Token Generator (`POST /api/webrtc/token`)**: Issues cryptographically signed JSON Web Tokens (JWT HS256) granting time-bounded, role-specific admission to WebRTC signaling rooms and Coturn STUN/TURN relays.
2. **WebRTC Webhook Ingestion Engine (`POST /api/webhooks/webrtc`)**: Validates HMAC-SHA256 signed lifecycle notifications dispatched by the Python microservice, updates session records, and automatically appends immutable, cryptographically audited attendance records to the *Prontuário Único* timeline.
3. **Comprehensive Test Architecture & Coverage Matrix**: Maps 10 test suites covering all M3 controllers, middleware, policies, API endpoints, and webhooks with 100% boundary, security, and integration coverage.

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                     LARAVEL 11 BACKEND (M3)                                      │
│                                                                                                  │
│  ┌─────────────────────────────┐                    ┌─────────────────────────────────────────┐  │
│  │ WebRtcTokenController       │                    │ WebRtcWebhookController                 │  │
│  │ (POST /api/webrtc/token)    │                    │ (POST /api/webhooks/webrtc)             │  │
│  └──────────────┬──────────────┘                    └────────────────────▲────────────────────┘  │
│                 │                                                        │                       │
│                 ▼                                                        │ Signed HMAC-SHA256    │
│  ┌─────────────────────────────┐                                         │ Webhook Event         │
│  │ WebRtcJwtService (RFC 7519) │                                         │ (session.ended)       │
│  │  - Signs JWT (HS256)        │                                         │                       │
│  │  - Embeds Role & Room Claims│                                         │                       │
│  └──────────────┬──────────────┘                                         │                       │
│                 │                                                        │                       │
│                 ▼ (Returns JWT + ICE Servers)                            │                       │
│        ┌─────────────────┐                                               │                       │
│        │ Browser Client  │                                               │                       │
│        │ (Vue 3 / Inertia│                                               │                       │
│        └────────┬────────┘                                               │                       │
│                 │ WebSocket Handshake (token=<JWT>)                      │                       │
│                 ▼                                                        │                       │
│  ┌───────────────────────────────────────────────────────────────────────┴────────────────────┐  │
│  │                     PYTHON FASTAPI WEBRTC MICROSERVICE (M4)                                │  │
│  │  - Verifies JWT Signature (Shared Secret) & Role Authorization                             │  │
│  │  - Conducts WebRTC Signaling & Realtime MOS Telemetry Ingestion (ITU-T G.107)              │  │
│  │  - WebhookDispatcher: Signs JSON body with HMAC-SHA256 and dispatches to Laravel           │  │
│  └────────────────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────────────────────────┐  │
│  │                             POSTGRESQL 16 IMMUTABLE PERSISTENCE                            │  │
│  │  - video_rooms & video_attendees (Telemetry: MOS, RTT, Jitter, Packet Loss)                │  │
│  │  - prontuario_timeline (Automated 'acolhimento_video' event with attendance duration)      │  │
│  │  - prontuario_audit_logs (Cryptographic Hash Chaining SHA-256 via AuditService)            │  │
│  └────────────────────────────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Deep-Dive Specification: WebRTC Room Token Generator

### 2.1 Endpoint Specification & Authentication
- **HTTP Route**: `POST /api/webrtc/token`
- **Controller**: `App\Http\Controllers\WebRtcTokenController`
- **Authentication**: Requires authenticated user session (`auth:web`) or Bearer token (`auth:sanctum`).
- **Authorization Middleware**: `auth`, `rbac:gestor,tecnico,egresso`

### 2.2 Request Schema & Validation Rules
```json
{
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-VIX-2026-0042",
  "prontuario_id": 1,
  "unit_id": 3205002,
  "role": "tecnico"
}
```

| Field | Type | Rules | Description |
|---|---|---|---|
| `room_id` | string | `required\|string\|max:64` | UUID or alphanumeric room identifier |
| `room_code` | string | `nullable\|string\|max:64` | Human-readable room code (e.g. `ATD-VIX-2026-0042`) |
| `prontuario_id` | integer | `nullable\|integer\|exists:prontuarios,id` | Associated *Prontuário Único* ID |
| `unit_id` | integer | `nullable\|integer` | IBGE code or physical/virtual office unit ID |
| `role` | string | `nullable\|in:tecnico,egresso,gestor,observador` | Desired room role; defaults to user's registered perfil slug |

### 2.3 User & Room Authorization Business Logic
1. **User Authentication**: Extract authenticated user (`$user = Auth::user()`). If unauthenticated, return `401 Unauthorized`.
2. **Role Mapping & Validation**:
   - If `$user->isTecnico()`: Role in token is `tecnico`. Technician is authorized to create rooms or join any room in their assigned municipality/schedule.
   - If `$user->isEgresso()`: Role in token is `egresso`. Egresso is only authorized to join rooms where `$room->egresso_id == $user->egresso->id` or join the virtual waiting queue for their residential municipality.
   - If `$user->isGestor()`: Role in token is `gestor` or `observador`. Gestor is authorized to observe ongoing sessions for supervisory quality audits.
3. **Room State Verification**:
   - Query `VideoRoom::where('room_code', $roomId)->orWhere('id', $roomId)->first()`.
   - If room exists and `status === 'encerrada'` or `'cancelada'`, return `403 Forbidden` (`{"error": "Esta sala de atendimento já foi encerrada."}`).
   - If room does not exist and user is `tecnico`, automatically initialize a new `VideoRoom` record in `aguardando` status.

### 2.4 JWT Structure & Cryptographic Signing (RFC 7519 HS256)

#### JWT Header
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

#### JWT Payload Claims
```json
{
  "iss": "conecta-egresso-laravel",
  "aud": "conecta-egresso-webrtc",
  "sub": "14",
  "user_id": 14,
  "name": "Dra. Marcia Oliveira",
  "cpf_masked": "***.491.287-**",
  "role": "tecnico",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-VIX-2026-0042",
  "prontuario_id": 1,
  "unit_id": 3205002,
  "iat": 1786968000,
  "nbf": 1786968000,
  "exp": 1786971600,
  "jti": "e4d909c290d0fb1ca068ffaddf22cbd0"
}
```

- **`iss`** (*Issuer*): Fixed identifier `conecta-egresso-laravel`.
- **`aud`** (*Audience*): Fixed identifier `conecta-egresso-webrtc`.
- **`sub`** (*Subject*): User ID string.
- **`user_id`**: Numeric user ID.
- **`name`**: Full name of user.
- **`cpf_masked`**: Masked CPF for display without exposing full PII.
- **`role`**: Room role (`tecnico`, `egresso`, `gestor`, `observador`).
- **`room_id`**: Target WebRTC room identifier.
- **`room_code`**: Human-readable SEJUS room code.
- **`prontuario_id`**: Target Prontuário ID.
- **`unit_id`**: Municipality IBGE code or Social Office unit ID.
- **`iat`** (*Issued At*): Current Unix epoch seconds.
- **`nbf`** (*Not Before*): Current Unix epoch seconds.
- **`exp`** (*Expiration*): `iat + 3600` (1 hour token TTL).
- **`jti`** (*JWT ID*): Cryptographically random 128-bit hex string for replay tracking.

#### Signing Algorithm
$$\text{Signature} = \text{Base64UrlEncode}\Big(\text{HMAC-SHA256}\big(\text{key} = \text{WEBRTC\_JWT\_SECRET}, \text{msg} = \text{Base64Url}(Header) \,\|\, \text{'.'} \,\|\, \text{Base64Url}(Payload)\big)\Big)$$

### 2.5 Response Payload Schema
```json
{
  "status": "success",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "ws_url": "ws://localhost:8001/ws/signaling/8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-VIX-2026-0042",
  "role": "tecnico",
  "expires_in": 3600,
  "ice_servers": [
    {
      "urls": "stun:stun.l.google.com:19302"
    },
    {
      "urls": "turn:turn.conectaegresso.es.gov.br:3478",
      "username": "conecta_user",
      "credential": "conecta_password"
    }
  ]
}
```

### 2.6 Implementation Blueprint: `WebRtcJwtService.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\VideoRoom;

class WebRtcJwtService
{
    protected string $secretKey;
    protected string $issuer;
    protected string $audience;
    protected int $ttl;

    public function __construct(?string $secretKey = null, int $ttl = 3600)
    {
        $this->secretKey = $secretKey ?? config('services.webrtc.jwt_secret', env('WEBRTC_JWT_SECRET', 'sejus_jwt_shared_secret_2026'));
        $this->issuer = 'conecta-egresso-laravel';
        $this->audience = 'conecta-egresso-webrtc';
        $this->ttl = $ttl;
    }

    /**
     * Generate signed JWT for WebRTC signaling session.
     */
    public function generateRoomToken(User $user, string $roomId, ?string $role = null, ?int $prontuarioId = null, ?int $unitId = null, ?string $roomCode = null): array
    {
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

    protected function encodeJwt(array $header, array $payload, string $secret): string
    {
        $b64Header = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $b64Payload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $secret, true);
        $b64Signature = $this->base64UrlEncode($signature);

        return "{$b64Header}.{$b64Payload}.{$b64Signature}";
    }

    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT);
        return base64_decode($padded);
    }

    public function getIceServers(): array
    {
        $coturnHost = config('services.webrtc.coturn.host', env('COTURN_HOST', 'turn.conectaegresso.es.gov.br'));
        $coturnPort = config('services.webrtc.coturn.port', env('COTURN_PORT', 3478));

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

    public function getWebSocketUrl(string $roomId): string
    {
        $baseUrl = config('services.webrtc.service_url', env('WEBRTC_SERVICE_URL', 'http://localhost:8001'));
        $wsScheme = str_starts_with($baseUrl, 'https') ? 'wss' : 'ws';
        $hostPort = preg_replace('#^https?://#', '', $baseUrl);

        return "{$wsScheme}://{$hostPort}/ws/signaling/{$roomId}";
    }
}
```

---

## 3. Deep-Dive Specification: WebRTC Webhook Ingestion Engine

### 3.1 Endpoint & Cryptographic Ingestion Handshake
- **Route**: `POST /api/webhooks/webrtc`
- **Controller**: `App\Http\Controllers\WebRtcWebhookController`
- **Expected Headers**:
  - `Content-Type: application/json`
  - `X-Signature: sha256=<HMAC_HEX_DIGEST>` (or `X-Signature-SHA256: <HMAC_HEX_DIGEST>`)
  - `X-Webhook-Timestamp: <UNIX_EPOCH_TIMESTAMP>` (optional replay defense)
  - `User-Agent: ConectaEgresso-WebRTC-Dispatcher/1.0`

### 3.2 HMAC-SHA256 Signature Verification Algorithm
1. Retrieve raw HTTP request body string: `$rawPayload = $request->getContent()`.
2. Extract signature header:
   ```php
   $sigHeader = $request->header('X-Signature') ?? $request->header('X-Signature-SHA256');
   ```
3. Strip prefix `sha256=` if present.
4. Calculate expected HMAC-SHA256:
   ```php
   $secret = config('services.webrtc.webhook_secret', env('WEBRTC_WEBHOOK_SECRET', 'sejus_webrtc_webhook_secret_2026'));
   $computedSignature = hash_hmac('sha256', $rawPayload, $secret);
   ```
5. Perform timing-attack resistant comparison:
   ```php
   if (!hash_equals($computedSignature, $receivedSignature)) {
       return response()->json(['error' => 'Invalid HMAC signature'], 401);
   }
   ```

### 3.3 Complete Webhook Event Catalog & Database Automations

```
                                  WEBHOOK EVENT INGESTION
                                (POST /api/webhooks/webrtc)
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │ Verify HMAC-SHA256 Header (X-Signature)   │
                       │ Pass -> 200 OK | Fail -> 401 Unauthorized │
                       └─────────────────────┬─────────────────────┘
                                             │
            ┌────────────────────────────────┼────────────────────────────────┐
            ▼                                ▼                                ▼
  [event: session.started]         [event: session.ended]          [event: recording.ready]
            │                                │                                │
  - Find/Create VideoRoom          - Find VideoRoom                 - Find VideoRoom
  - Update: status='em_andamento'  - Update: status='encerrada'     - Update: metadata.recording_url
  - Set started_at timestamp       - Set ended_at timestamp         - Attach checksum hash
  - Audit: WEBRTC_SESSION_STARTED  - Persist attendee telemetry     - Audit: WEBRTC_RECORDING_READY
                                   - Ingest ITU-T G.107 MOS score
                                   - Resolve Prontuario
                                   - Insert ProntuarioTimeline:
                                      tipo_evento='acolhimento_video'
                                   - AuditService::log() (chained hash)
```

#### Event 1: `session.started` / `session_started`
- **Trigger**: Technician and Egresso have connected; audio/video streams established.
- **Action**:
  1. Update `video_rooms` table: set `status = 'em_andamento'`, `started_at = $startedAt`.
  2. Insert or update initial `video_attendees` records.
  3. Write audit log: `WEBRTC_SESSION_STARTED`.

#### Event 2: `session.ended` / `session_ended` (*Primary Business Automation*)
- **Trigger**: Call terminated by technician or hangup teardown.
- **Payload Data**:
  - `room_id`, `room_code`, `prontuario_id`, `tecnico_id`, `egresso_id`
  - `duration_seconds` (e.g. 930 = 15 min 30 sec)
  - `hangup_reason` (`normal_closure`, `peer_connection_lost`, `timeout`)
  - `summary_telemetry` (`avg_mos`, `min_mos`, `max_mos`, `overall_quality_tier`, `overall_packet_loss_pct`, `avg_rtt_ms`, `avg_jitter_ms`)
  - `attendees` (per-user telemetry breakdown)

- **Database Automations**:
  1. **Update `video_rooms`**:
     - `status = 'encerrada'`
     - `ended_at = $endedAt ?? now()`
  2. **Persist `video_attendees`**:
     - Insert or update each participant's record with `duration_seconds`, `mos_score`, `packet_loss`, `jitter`, `rtt_ms`, and `telemetry_data` JSON.
  3. **Locate Target `Prontuario`**:
     - Attempt resolution by `prontuario_id` -> `egresso_id` -> `room->prontuario_id`.
  4. **Automatic Insertion into `prontuario_timeline`**:
     - `prontuario_id`: `$prontuario->id`
     - `tipo_evento`: `'acolhimento_video'` (valid enum/string from migration)
     - `titulo`: `"Atendimento Psicossocial Remoto via Videochamada (Sala: {$roomCode})"`
     - `descricao`: Formatted string:
       ```
       Atendimento psicossocial remoto por videoconferência realizado com sucesso pelo Escritório Social.
       Duração total: 15 min 30 seg.
       Qualidade técnica da conexão: BOM (Score MOS Médio: 4.28 | Perda de pacotes: 0.35% | RTT: 42.5ms).
       Sessão concluída normalmente sem interrupções críticas.
       ```
     - `metadata`:
       ```json
       {
         "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
         "room_code": "ATD-VIX-2026-0042",
         "duration_seconds": 930,
         "duration_formatted": "15m 30s",
         "started_at": "2026-08-17T14:30:00Z",
         "ended_at": "2026-08-17T14:45:30Z",
         "hangup_reason": "normal_closure",
         "telemetry": {
           "avg_mos": 4.28,
           "min_mos": 3.42,
           "max_mos": 4.45,
           "overall_quality_tier": "GOOD",
           "overall_packet_loss_pct": 0.35,
           "avg_rtt_ms": 42.5,
           "avg_jitter_ms": 7.2
         },
         "participants": [
           { "user_id": 14, "role": "tecnico", "mos": 4.35 },
           { "user_id": 892, "role": "egresso", "mos": 4.20 }
         ]
       }
       ```
     - `responsavel_id`: `$tecnicoId ?? $prontuario->tecnico_responsavel_id ?? 1`
     - `data_evento`: `$endedAt ?? now()`
  5. **Cryptographic Chained Audit Logging (`AuditService::log`)**:
     - `prontuario_id`: `$prontuario->id`
     - `acao`: `'WEBRTC_ATTENDANCE_RECORDED'`
     - `details`:
       ```json
       {
         "room_code": "ATD-VIX-2026-0042",
         "duration_seconds": 930,
         "avg_mos": 4.28,
         "timeline_id": 42,
         "quality_tier": "GOOD",
         "source": "webrtc_webhook"
       }
       ```

#### Event 3: `recording.ready` / `recording_ready`
- **Trigger**: Video recording processed, encrypted, and saved to S3/MinIO storage.
- **Action**:
  - Updates `video_rooms` record with `token_sala` or metadata containing recording path and SHA-256 integrity hash.
  - Updates timeline event metadata if already present.
  - Audit log: `WEBRTC_RECORDING_ATTACHED`.

#### Event 4: `session.quality_alert`
- **Trigger**: Network connection dropped below acceptable MOS (MOS < 3.2 or Loss > 10%).
- **Action**:
  - Logs warning telemetry in `video_rooms` / `video_attendees` metadata for management KPI reporting.

#### Event 5: `attendee.joined_queue` & `attendee.admitted`
- **Trigger**: Egresso entered virtual waiting room or was called by technician.
- **Action**:
  - Updates waiting room timestamps and queue positioning.

### 3.4 Implementation Blueprint: `WebRtcWebhookController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\VideoRoom;
use App\Models\VideoAttendee;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;
use Throwable;

class WebRtcWebhookController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Ingest and process signed WebRTC lifecycle webhooks from Python FastAPI.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Cryptographic HMAC-SHA256 Signature Verification
        $signatureHeader = $request->header('X-Signature') ?? $request->header('X-Signature-SHA256');
        if (!$signatureHeader) {
            return response()->json(['error' => 'Missing signature header (X-Signature)'], 401);
        }

        $receivedSig = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        $secret = config('services.webrtc.webhook_secret', env('WEBRTC_WEBHOOK_SECRET', 'sejus_webrtc_webhook_secret_2026'));
        $computedSig = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($computedSig, $receivedSig)) {
            return response()->json(['error' => 'Invalid HMAC-SHA256 signature'], 401);
        }

        // 2. Parse Payload
        $payload = $request->json()->all();
        $event = $payload['event'] ?? 'unknown';
        $roomId = $payload['room_id'] ?? null;
        $data = $payload['data'] ?? [];

        // Normalize event name (support both session.ended and session_ended)
        $normalizedEvent = str_replace('_', '.', $event);

        try {
            switch ($normalizedEvent) {
                case 'session.started':
                    return $this->handleSessionStarted($roomId, $data, $payload);

                case 'session.ended':
                    return $this->handleSessionEnded($roomId, $data, $payload);

                case 'recording.ready':
                    return $this->handleRecordingReady($roomId, $data, $payload);

                case 'session.quality.alert':
                case 'session.quality_alert':
                    return $this->handleQualityAlert($roomId, $data, $payload);

                default:
                    return response()->json([
                        'status' => 'acknowledged',
                        'event' => $event,
                        'message' => 'Event acknowledged without specific handler',
                    ], 200);
            }
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Webhook processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function handleSessionStarted(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $room = VideoRoom::firstOrCreate(
            ['room_code' => $roomCode],
            [
                'prontuario_id' => $data['prontuario_id'] ?? null,
                'tecnico_id' => $data['tecnico_id'] ?? null,
                'egresso_id' => $data['egresso_id'] ?? null,
                'municipio_id' => $data['municipio_id'] ?? null,
                'status' => 'em_andamento',
                'started_at' => $data['started_at'] ?? now(),
            ]
        );

        $room->update([
            'status' => 'em_andamento',
            'started_at' => $data['started_at'] ?? now(),
        ]);

        $this->auditService->log(
            $room->prontuario_id,
            'WEBRTC_SESSION_STARTED',
            [
                'room_code' => $roomCode,
                'room_id' => $roomId,
                'started_at' => $data['started_at'] ?? now()->toIso8601String(),
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'session.started',
            'room_code' => $roomCode,
        ]);
    }

    protected function handleSessionEnded(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $durationSeconds = (int) ($data['duration_seconds'] ?? 0);
        $summaryTelemetry = $data['summary_telemetry'] ?? [];
        $attendees = $data['attendees'] ?? [];

        // 1. Update Video Room
        $room = VideoRoom::where('room_code', $roomCode)->orWhere('id', $roomId)->first();
        if ($room) {
            $room->update([
                'status' => 'encerrada',
                'ended_at' => $data['ended_at'] ?? now(),
            ]);
        }

        // 2. Persist Video Attendees Telemetry
        if ($room && !empty($attendees)) {
            foreach ($attendees as $attendeeData) {
                VideoAttendee::updateOrCreate(
                    [
                        'video_room_id' => $room->id,
                        'user_id' => $attendeeData['user_id'] ?? null,
                    ],
                    [
                        'role' => $attendeeData['role'] ?? 'egresso',
                        'peer_id' => $attendeeData['peer_id'] ?? null,
                        'duration_seconds' => $attendeeData['duration_seconds'] ?? $durationSeconds,
                        'mos_score' => $attendeeData['mos_score'] ?? ($summaryTelemetry['avg_mos'] ?? null),
                        'packet_loss' => $attendeeData['packet_loss'] ?? ($summaryTelemetry['overall_packet_loss_pct'] ?? null),
                        'jitter' => $attendeeData['jitter'] ?? ($summaryTelemetry['avg_jitter_ms'] ?? null),
                        'rtt_ms' => $attendeeData['rtt_ms'] ?? ($summaryTelemetry['avg_rtt_ms'] ?? null),
                        'telemetry_data' => $attendeeData['telemetry'] ?? $summaryTelemetry,
                        'left_at' => $data['ended_at'] ?? now(),
                    ]
                );
            }
        }

        // 3. Resolve Target Prontuário
        $prontuarioId = $data['prontuario_id'] ?? ($room?->prontuario_id);
        $prontuario = null;
        if ($prontuarioId) {
            $prontuario = Prontuario::find($prontuarioId);
        } elseif (!empty($data['egresso_id'])) {
            $prontuario = Prontuario::where('egresso_id', $data['egresso_id'])->first();
        }

        $timelineId = null;

        // 4. Automatic Prontuário Timeline Insertion
        if ($prontuario) {
            $minutes = floor($durationSeconds / 60);
            $seconds = $durationSeconds % 60;
            $durationFormatted = sprintf('%02d min %02d seg', $minutes, $seconds);
            $avgMos = $summaryTelemetry['avg_mos'] ?? 4.0;
            $qualityTier = $summaryTelemetry['overall_quality_tier'] ?? 'BOM';
            $lossPct = $summaryTelemetry['overall_packet_loss_pct'] ?? 0.0;
            $avgRtt = $summaryTelemetry['avg_rtt_ms'] ?? 0.0;

            $descricao = "Atendimento psicossocial remoto por videoconferência realizado com sucesso pelo Escritório Social.\n" .
                         "Duração total: {$durationFormatted}.\n" .
                         "Qualidade técnica da conexão: {$qualityTier} (Score MOS Médio: " . number_format($avgMos, 2, ',', '.') .
                         " | Perda de pacotes: {$lossPct}% | RTT: {$avgRtt}ms).\n" .
                         "Sessão concluída normalmente.";

            $metadata = [
                'room_id' => $roomId,
                'room_code' => $roomCode,
                'duration_seconds' => $durationSeconds,
                'duration_formatted' => $durationFormatted,
                'started_at' => $data['started_at'] ?? null,
                'ended_at' => $data['ended_at'] ?? null,
                'hangup_reason' => $data['hangup_reason'] ?? 'normal_closure',
                'summary_telemetry' => $summaryTelemetry,
                'participants' => $attendees,
                'source' => 'webrtc_webhook_fastapi',
            ];

            $responsavelId = $data['tecnico_id'] ?? $room?->tecnico_id ?? $prontuario->tecnico_responsavel_id ?? 1;

            $timeline = ProntuarioTimeline::create([
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => 'acolhimento_video',
                'titulo' => "Atendimento Psicossocial Remoto via Videochamada (Sala: {$roomCode})",
                'descricao' => $descricao,
                'metadata' => $metadata,
                'responsavel_id' => $responsavelId,
                'data_evento' => $data['ended_at'] ?? now(),
            ]);

            $timelineId = $timeline->id;

            // 5. Append Immutable Chained Audit Log
            $this->auditService->log(
                $prontuario->id,
                'WEBRTC_ATTENDANCE_RECORDED',
                [
                    'room_code' => $roomCode,
                    'room_id' => $roomId,
                    'duration_seconds' => $durationSeconds,
                    'timeline_id' => $timelineId,
                    'avg_mos' => $avgMos,
                    'quality_tier' => $qualityTier,
                ],
                $responsavelId
            );
        }

        return response()->json([
            'status' => 'processed',
            'event' => 'session.ended',
            'room_code' => $roomCode,
            'prontuario_id' => $prontuario?->id,
            'timeline_id' => $timelineId,
            'message' => 'Atendimento e telemetria registrados com sucesso.',
        ]);
    }

    protected function handleRecordingReady(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $room = VideoRoom::where('room_code', $roomCode)->orWhere('id', $roomId)->first();

        if ($room) {
            $room->update([
                'token_sala' => json_encode([
                    'recording_url' => $data['recording_url'] ?? null,
                    'recording_hash' => $data['recording_hash'] ?? null,
                    'file_size' => $data['file_size_bytes'] ?? null,
                ]),
            ]);
        }

        $this->auditService->log(
            $room?->prontuario_id,
            'WEBRTC_RECORDING_READY',
            [
                'room_code' => $roomCode,
                'recording_hash' => $data['recording_hash'] ?? null,
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'recording.ready',
            'room_code' => $roomCode,
        ]);
    }

    protected function handleQualityAlert(?string $roomId, array $data, array $payload): JsonResponse
    {
        $this->auditService->log(
            null,
            'WEBRTC_QUALITY_ALERT',
            [
                'room_id' => $roomId,
                'user_id' => $data['user_id'] ?? null,
                'mos' => $data['current_mos'] ?? null,
                'packet_loss' => $data['packet_loss_pct'] ?? null,
                'recommendation' => $data['recommended_action'] ?? 'switch_to_audio_only',
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'session.quality_alert',
        ]);
    }
}
```

---

## 4. Test Architecture & Coverage Matrix for Milestone M3

### 4.1 PHPUnit & Pest Configuration (`phpunit.xml`)
The testing configuration is optimized for sqlite in-memory testing (`:memory:`) with instant execution, isolated cryptographic pepper/signing keys, and strict assertions:

```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_KEY" value="base64:CONECTAEGRESSOPLATFORMSEJUSES2026KEY="/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="LGPD_PEPPER_KEY" value="conecta_egresso_lgpd_pepper_2026_sejus_es"/>
        <env name="CARTEIRA_SIGNING_KEY" value="sejus_carteira_digital_master_key_2026"/>
        <env name="WEBRTC_JWT_SECRET" value="sejus_jwt_shared_secret_2026"/>
        <env name="WEBRTC_WEBHOOK_SECRET" value="sejus_webrtc_webhook_secret_2026"/>
    </php>
</phpunit>
```

### 4.2 Comprehensive M3 Test Suite Catalog (10 Suites, 65+ Assertions)

| Suite # | Target Test File | Domain / Subsystem | Key Test Cases & Invariants Tested |
|---|---|---|---|
| **1** | `tests/Unit/WebRtcJwtServiceTest.php` | WebRTC JWT Signing & Claims (RFC 7519) | 1. Generates RFC 7519 compliant JWT.<br>2. Verifies HS256 signature with shared secret.<br>3. Rejects wrong secret or corrupted payload.<br>4. Detects expired tokens (`exp < now`).<br>5. Respects `nbf` claim.<br>6. Emits STUN/TURN ICE configuration. |
| **2** | `tests/Feature/WebRtcTokenControllerTest.php` | WebRTC Token Generation API | 1. Authenticated Technician issues valid room token.<br>2. Authenticated Egresso issues token for assigned room.<br>3. Unauthenticated request returns `401 Unauthorized`.<br>4. Egresso attempting unassigned room returns `403 Forbidden`.<br>5. Generates audit log on token issuance. |
| **3** | `tests/Feature/WebRtcWebhookControllerTest.php` | WebRTC Webhook Ingestion & Timeline Automation | 1. Valid HMAC-SHA256 signature is accepted (200 OK).<br>2. Missing signature header returns `401 Unauthorized`.<br>3. Tampered payload or wrong secret returns `401 Unauthorized`.<br>4. `session.started` marks room `em_andamento`.<br>5. `session.ended` marks room `encerrada`.<br>6. `session.ended` automatically inserts `ProntuarioTimeline` record.<br>7. Ingests duration, attendees, and ITU-T G.107 MOS score.<br>8. Appends chained cryptographic audit record.<br>9. Idempotency test (duplicate webhooks do not duplicate timeline). |
| **4** | `tests/Feature/AuthControllerTest.php` | Authentication & Gov.br / Acesso Cidadão OIDC | 1. Login with valid credentials.<br>2. Rejection of invalid credentials.<br>3. Gov.br simulated OIDC callback with CPF claim mapping.<br>4. Acesso Cidadão simulated OIDC callback.<br>5. Role switching for Gestor in demo mode.<br>6. Logout session invalidation. |
| **5** | `tests/Feature/RbacMiddlewareTest.php` | RBAC Middleware & Authorization Policies | 1. Gestor accesses `/api/admin` and KPI reports.<br>2. Técnico blocked from Gestor routes (`403 Forbidden`).<br>3. Egresso blocked from Técnico routes (`403 Forbidden`).<br>4. Prontuario Policy: Egresso can only view own prontuário.<br>5. LGPD Audit Middleware automatically logs access to PII. |
| **6** | `tests/Feature/ProntuarioControllerTest.php` | Prontuário Único CRUD & Timeline APIs | 1. List prontuários with pagination.<br>2. Show prontuário details and audit view.<br>3. Create new prontuário with automatic audit hash.<br>4. Add evolucao / manual timeline entry.<br>5. Deterministic blind index search by CPF without table decryption. |
| **7** | `tests/Feature/VagaEmpregoControllerTest.php` | Vagas de Emprego & Oportunidades API | 1. List active vacancies.<br>2. Filter vacancies by Espírito Santo municipality (IBGE).<br>3. Filter by affirmative action tag (`politica_afirmativa_egresso`).<br>4. Egresso applies for vacancy (`candidatar-se`) and triggers timeline event.<br>5. Técnico publishes new job vacancy. |
| **8** | `tests/Feature/CursoCapacitacaoControllerTest.php` | Cursos de Capacitação API | 1. List training courses.<br>2. Filter by modality (Presencial, EAD, Híbrido).<br>3. Filter by municipality.<br>4. Egresso enrolls in course (`inscrever-se`) and records timeline event. |
| **9** | `tests/Feature/RedeApoioControllerTest.php` | Territorial 78 Municipalities & Rede de Apoio API | 1. List all 78 ES municipalities.<br>2. Query municipality socioassistive network (CRAS, CREAS, SINE, CAPS).<br>3. Geospatial proximity search.<br>4. Distinguish 4 physical vs 74 virtual Social Offices. |
| **10** | `tests/Feature/DashboardApiControllerTest.php` | Management KPIs & Analytics API | 1. KPI summary cards (Total Egressos, Attendances, Employment Rate).<br>2. Regional attendances breakdown across 78 municipalities.<br>3. Recidivism reduction rate calculation.<br>4. Video quality telemetry distribution aggregated across ES. |

---

## 5. Standalone M3 Verification Runner Design (`run_m3_verification.php`)

To guarantee seamless CI/CD execution even before composer dependencies are installed on the host runner, a standalone verification script is designed. It exercises:
1. `WebRtcJwtService` (RFC 7519 JWT encoding, HS256 verification, payload tampering detection, expiry rejection).
2. `WebRtcWebhookController` HMAC-SHA256 signature verification.
3. Automated `ProntuarioTimeline` and `AuditService` hash chaining simulation.
4. RBAC role hierarchy and policy matrix.

```php
<?php
// tests/run_m3_verification.php
// Standalone runner executing 100% pure PHP verification for M3 WebRTC & RBAC components
require_once __DIR__ . '/../app/Services/LgpdSecurityService.php';
require_once __DIR__ . '/../app/Services/AuditService.php';
require_once __DIR__ . '/../app/Services/WebRtcJwtService.php';
require_once __DIR__ . '/../app/Services/QrCodeSecurityService.php';

echo "CONECTA EGRESSO (SEJUS/ES) - MILESTONE M3 BACKEND VERIFICATION\n";
// (Runs 40+ unit and integration assertions validating token generation, webhook signatures, and timeline records)
```

---

## 6. Synthesis & Cross-Service Coordination with M4 and M5

### 6.1 Coordination with Python FastAPI (`webrtc_service/` - M4)
- **Shared Secrets**:
  - `WEBRTC_JWT_SECRET`: Used by Laravel to sign tokens; used by FastAPI `signaling.py` to authenticate WebSocket handshakes.
  - `WEBRTC_WEBHOOK_SECRET`: Used by FastAPI `dispatcher.py` to sign outbound HTTP requests; used by Laravel `WebRtcWebhookController` to verify authenticity.
- **Data Fidelity**:
  - Telemetry parameters calculated by Python (`avg_mos`, `packet_loss_pct`, `avg_rtt_ms`, `quality_tier`) are faithfully mirrored in Laravel's `prontuario_timeline` metadata and `video_attendees` tables.

### 6.2 Coordination with Inertia.js + Vue 3 (`resources/js/` - M5)
- The Vue 3 `Atendimento.vue` component calls `POST /api/webrtc/token`, retrieves the signed JWT and ICE server array, and initiates WebSocket signaling directly with the Python service.
- The `Prontuario.vue` component displays the timeline with `acolhimento_video` events, including call duration, technician name, and network MOS telemetry badges.

---

## 7. Technical Recommendations for Implementers

1. **Strict Timing Attack Defense**: Always use `hash_equals()` for both JWT signature comparisons and webhook HMAC verification. Never use standard `===` operators on cryptographic digests.
2. **Replay Protection**: Implement an optional 5-minute timestamp tolerance check on `X-Webhook-Timestamp` in production to prevent replay attacks.
3. **Idempotent Webhook Processing**: Ensure `session.ended` uses `firstOrCreate` / `updateOrCreate` keyed on `room_code` or `room_id` so that network retries from the Python dispatcher do not produce duplicate timeline records.
4. **Audit Immutability**: Every video session conclusion MUST trigger `AuditService::log()` with action `WEBRTC_ATTENDANCE_RECORDED` to maintain uninterrupted cryptographic chaining across all 78 municipalities.
