<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MobileAccessToken;
use App\Models\ProntuarioAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TokenRefreshAdversarialChallengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to authenticate as a user and retrieve Bearer token.
     */
    private function getMobileToken(
        string $login = 'lucas.santos@cidadao.es.gov.br',
        string $password = 'secret123',
        string $deviceName = 'Challenger Adversarial Harness'
    ): string {
        $response = $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => $deviceName,
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token, 'Failed to acquire access_token during login helper.');

        return (string) $token;
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 1: Immediate Replay Attacks & Revocation Integrity
    |--------------------------------------------------------------------------
    */

    /**
     * Replay Attack Test: Using an old token after refresh must strictly return 401 UNAUTHENTICATED
     * across all endpoints (refresh, me, business resources, wallet).
     */
    public function test_adversarial_immediate_replay_attack_on_old_token_is_strictly_rejected_401(): void
    {
        $oldToken = $this->getMobileToken();
        $oldTokenHash = hash('sha256', $oldToken);

        // Pre-condition: Old token exists and works
        $this->assertDatabaseHas('mobile_access_tokens', ['token' => $oldTokenHash]);
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);

        // Execute refresh -> receive newToken
        $refreshResponse = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');

        $refreshResponse->assertStatus(200)
            ->assertJson([
                'status' => 'refreshed',
                'token_type' => 'Bearer',
            ]);

        $newToken = $refreshResponse->json('access_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);

        // Post-condition 1: Old token record is deleted from database
        $this->assertDatabaseMissing('mobile_access_tokens', ['token' => $oldTokenHash]);

        // Post-condition 2: Immediate Replay on POST /api/auth/refresh must return 401 UNAUTHENTICATED
        $replayRefresh = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');
        $replayRefresh->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Post-condition 3: Immediate Replay on GET /api/auth/me must return 401 UNAUTHENTICATED
        $replayMe = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/auth/me');
        $replayMe->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Post-condition 4: Immediate Replay on business resource (candidaturas) must return 401 UNAUTHENTICATED
        $replayCandidaturas = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/candidaturas');
        $replayCandidaturas->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Post-condition 5: Immediate Replay on carteira must return 401 UNAUTHENTICATED
        $replayCarteira = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/carteira');
        $replayCarteira->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Post-condition 6: New token works on all endpoints
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/candidaturas')
            ->assertStatus(200);
    }

    /**
     * Replay Attack Stress: Repeated rapid attempts (10x) with the revoked token must all return 401,
     * without generating orphan tokens or causing unhandled exceptions.
     */
    public function test_adversarial_repeated_rapid_replay_attempts_on_revoked_token_remain_blocked(): void
    {
        $oldToken = $this->getMobileToken();

        // Refresh once
        $refreshResponse = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');
        $refreshResponse->assertStatus(200);
        $validToken = $refreshResponse->json('access_token');

        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $tokenCountBefore = MobileAccessToken::where('user_id', $user->id)->count();

        // Fire 10 rapid replay requests with the old token
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
                ->postJson('/api/auth/refresh');

            $response->assertStatus(401)
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }

        // Verify no extra tokens were issued during the 10 failed replays
        $tokenCountAfter = MobileAccessToken::where('user_id', $user->id)->count();
        $this->assertEquals($tokenCountBefore, $tokenCountAfter, 'Failed replays must not generate new tokens.');

        // Verify valid token remains fully functional
        $this->withHeader('Authorization', 'Bearer ' . $validToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }

    /**
     * Multi-Generation Token Rotation Chain: Rotating tokens 5 times (T0 -> T1 -> T2 -> T3 -> T4)
     * must leave strictly 1 active token in the database, and all 4 ancestor tokens must fail with 401.
     */
    public function test_adversarial_chained_multi_generation_rotation_invalidates_all_ancestor_tokens(): void
    {
        $tokens = [];
        $tokens[0] = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // Perform 4 successive rotations: T0 -> T1 -> T2 -> T3 -> T4
        for ($gen = 0; $gen < 4; $gen++) {
            $resp = $this->withHeader('Authorization', 'Bearer ' . $tokens[$gen])
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(200);
            $tokens[$gen + 1] = $resp->json('access_token');
            $this->assertNotEmpty($tokens[$gen + 1]);
            $this->assertNotEquals($tokens[$gen], $tokens[$gen + 1]);
        }

        // Invariant: The user must have exactly 1 token record in mobile_access_tokens
        $activeUserTokens = MobileAccessToken::where('user_id', $user->id)->get();
        $this->assertCount(1, $activeUserTokens, 'Only the latest token in the chain must exist.');
        $this->assertEquals(hash('sha256', $tokens[4]), $activeUserTokens->first()->token);

        // Every ancestor token (T0, T1, T2, T3) must be rejected with 401 on refresh and me
        for ($gen = 0; $gen < 4; $gen++) {
            $this->withHeader('Authorization', 'Bearer ' . $tokens[$gen])
                ->postJson('/api/auth/refresh')
                ->assertStatus(401)
                ->assertJson(['code' => 'UNAUTHENTICATED']);

            $this->withHeader('Authorization', 'Bearer ' . $tokens[$gen])
                ->getJson('/api/auth/me')
                ->assertStatus(401)
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }

        // Only the latest generation (T4) must succeed
        $this->withHeader('Authorization', 'Bearer ' . $tokens[4])
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 2: Tampered Tokens & Signature Manipulation
    |--------------------------------------------------------------------------
    */

    /**
     * Tampered Token: Bit flips or single-character alterations in the token body must strictly return 401.
     */
    public function test_adversarial_tampered_token_hash_mutation_rejected_401(): void
    {
        $validToken = $this->getMobileToken();

        // Tamper character at index 7 (start of hex payload)
        $tamperedStart = substr_replace($validToken, $validToken[7] === 'a' ? 'b' : 'a', 7, 1);
        $this->withHeader('Authorization', 'Bearer ' . $tamperedStart)
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Tamper character at index 35 (middle of hex payload)
        $tamperedMid = substr_replace($validToken, $validToken[35] === '0' ? '1' : '0', 35, 1);
        $this->withHeader('Authorization', 'Bearer ' . $tamperedMid)
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Tamper character at the very end
        $lastIdx = strlen($validToken) - 1;
        $tamperedEnd = substr_replace($validToken, $validToken[$lastIdx] === 'f' ? 'e' : 'f', $lastIdx, 1);
        $this->withHeader('Authorization', 'Bearer ' . $tamperedEnd)
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /**
     * Tampered Token: Prefix mutations (missing, truncated, altered, duplicated) must strictly return 401.
     */
    public function test_adversarial_tampered_token_prefix_manipulations_rejected_401(): void
    {
        $validToken = $this->getMobileToken();
        $hexOnly = substr($validToken, 7); // Strip 'ce_mob_'

        $prefixMutations = [
            'hex_only_without_prefix' => $hexOnly,
            'truncated_prefix_no_underscore' => 'ce_mob' . $hexOnly,
            'wrong_prefix_api' => 'ce_api_' . $hexOnly,
            'wrong_prefix_web' => 'ce_web_' . $hexOnly,
            'wrong_prefix_sejus' => 'sejus_' . $hexOnly,
            'duplicated_prefix' => 'ce_mob_ce_mob_' . $hexOnly,
            'prefix_only' => 'ce_mob_',
            'prefix_with_single_char' => 'ce_mob_a',
            'prefix_with_half_length' => 'ce_mob_' . substr($hexOnly, 0, 16),
        ];

        foreach ($prefixMutations as $label => $token) {
            $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(401, "Prefix manipulation [{$label}] did not return 401.")
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }
    }

    /**
     * Tampered Token: Arbitrary hex strings, UUIDs, and random tokens must return 401.
     */
    public function test_adversarial_tampered_token_arbitrary_and_boundary_strings_rejected_401(): void
    {
        $arbitraryTokens = [
            'sha256_hex_string' => hash('sha256', 'arbitrary_payload_attempt'),
            'md5_hex_string' => md5('arbitrary_payload_attempt'),
            'uuid_v4' => (string) Str::uuid(),
            'repeated_hex_zeroes' => 'ce_mob_' . str_repeat('0', 64),
            'repeated_hex_f' => 'ce_mob_' . str_repeat('f', 64),
            'short_token' => 'ce_mob_12345',
            'oversized_payload_10k' => 'ce_mob_' . str_repeat('deadbeef', 1250),
        ];

        foreach ($arbitraryTokens as $label => $token) {
            $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(401, "Arbitrary string test [{$label}] did not return 401.")
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }
    }

    /**
     * Tampered Token: Injection attacks (SQLi, XSS, Null bytes, Directory Traversal) inside token string.
     */
    public function test_adversarial_injection_payloads_in_token_rejected_401(): void
    {
        $injections = [
            'sqli_or_1_equals_1' => "ce_mob_' OR '1'='1",
            'sqli_drop_table' => "ce_mob_'; DROP TABLE mobile_access_tokens; --",
            'sqli_union_select' => "ce_mob_' UNION SELECT * FROM users --",
            'xss_script_tag' => "ce_mob_<script>alert('pwned')</script>",
            'xss_img_onerror' => "ce_mob_\"><img src=x onerror=alert(1)>",
            'null_byte_middle' => "ce_mob_abc" . chr(0) . "def1234567890",
            'path_traversal' => "ce_mob_../../../../etc/passwd",
            'crlf_header_injection' => "ce_mob_test\r\nX-Injected-Header: true",
        ];

        foreach ($injections as $label => $token) {
            $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(401, "Injection payload [{$label}] must return 401 without 500 error.")
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 3: Expired Tokens & Expiration Boundaries
    |--------------------------------------------------------------------------
    */

    /**
     * Expired Tokens: Tokens past 30 days expiration or past their expiration boundary must return 401.
     * The database record must NOT be deleted or refreshed.
     */
    public function test_adversarial_expired_tokens_beyond_and_at_boundary_rejected_401(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        $expiredScenarios = [
            'expired_31_days_ago' => Carbon::now()->subDays(31),
            'expired_30_days_ago' => Carbon::now()->subDays(30),
            'expired_7_days_ago' => Carbon::now()->subDays(7),
            'expired_1_hour_ago' => Carbon::now()->subHour(),
            'expired_1_minute_ago' => Carbon::now()->subMinute(),
            'expired_1_second_ago' => Carbon::now()->subSecond(),
        ];

        foreach ($expiredScenarios as $label => $expiredTimestamp) {
            $tokenData = MobileAccessToken::createTokenForUser(
                $user,
                "Expired Test [{$label}]",
                $expiredTimestamp
            );
            $rawToken = $tokenData['plainTextToken'];
            $tokenHash = hash('sha256', $rawToken);

            $resp = $this->withHeader('Authorization', 'Bearer ' . $rawToken)
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(401, "Expired token scenario [{$label}] did not return 401.")
                ->assertJson(['code' => 'UNAUTHENTICATED']);

            // Verify the expired token was NOT deleted from DB by the failed refresh attempt
            $this->assertDatabaseHas('mobile_access_tokens', [
                'token' => $tokenHash,
            ]);
        }
    }

    /**
     * Boundary Expiration Test: Token expiring in 10 seconds is VALID; once past, it is INVALID.
     */
    public function test_adversarial_token_expiration_exact_boundary(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // 1. Token expiring 10 seconds in the future -> must be accepted
        $validData = MobileAccessToken::createTokenForUser(
            $user,
            'Boundary Valid Phone',
            Carbon::now()->addSeconds(10)
        );

        $validResp = $this->withHeader('Authorization', 'Bearer ' . $validData['plainTextToken'])
            ->postJson('/api/auth/refresh');

        $validResp->assertStatus(200)
            ->assertJson(['status' => 'refreshed']);

        // 2. Token created with expiration 1 second in the past -> must be rejected
        $expiredData = MobileAccessToken::createTokenForUser(
            $user,
            'Boundary Expired Phone',
            Carbon::now()->subSecond()
        );

        $expiredResp = $this->withHeader('Authorization', 'Bearer ' . $expiredData['plainTextToken'])
            ->postJson('/api/auth/refresh');

        $expiredResp->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 4: Deactivated Account Enforcement & RBAC State Transitions
    |--------------------------------------------------------------------------
    */

    /**
     * Deactivated Account: Attempting to refresh with an active token for an inactive user (ativo=false)
     * must strictly return 403 ACCOUNT_DEACTIVATED.
     */
    public function test_adversarial_deactivated_account_refresh_strictly_returns_403_account_deactivated(): void
    {
        $token = $this->getMobileToken();
        $tokenHash = hash('sha256', $token);
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // Deactivate account
        $user->ativo = false;
        $user->save();

        $auditCountBefore = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();

        // Attempt refresh
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_DEACTIVATED',
            ]);

        // Verify token was NOT refreshed (no audit log created)
        $auditCountAfter = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();
        $this->assertEquals($auditCountBefore, $auditCountAfter);

        // Verify original token is still in DB (not mutated or replaced)
        $this->assertDatabaseHas('mobile_access_tokens', [
            'token' => $tokenHash,
        ]);
    }

    /**
     * Deactivation Lifecycle: An account deactivated and then reactivated preserves token functionality.
     */
    public function test_adversarial_deactivation_and_reactivation_lifecycle(): void
    {
        $token = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // 1. Deactivate -> 403
        $user->update(['ativo' => false]);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh')
            ->assertStatus(403)
            ->assertJson(['code' => 'ACCOUNT_DEACTIVATED']);

        // 2. Reactivate -> 200 OK (refresh succeeds)
        $user->update(['ativo' => true]);
        $refreshResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');
        $refreshResp->assertStatus(200)
            ->assertJson(['status' => 'refreshed']);

        $newToken = $refreshResp->json('access_token');

        // 3. Old token is now dead (replay) -> 401
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4. New token works
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }

    /**
     * Defense-in-Depth: Deactivated account with an expired or invalid token returns 401 first.
     */
    public function test_adversarial_deactivated_account_with_tampered_or_expired_token(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $user->update(['ativo' => false]);

        // Case A: Expired token on deactivated account
        $expiredData = MobileAccessToken::createTokenForUser(
            $user,
            'Deactivated Expired',
            Carbon::now()->subMinutes(30)
        );

        $this->withHeader('Authorization', 'Bearer ' . $expiredData['plainTextToken'])
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Case B: Tampered token on deactivated account
        $this->withHeader('Authorization', 'Bearer ce_mob_invalid_tampered_token_for_deactivated')
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 5: Missing & Malformed Authorization Headers
    |--------------------------------------------------------------------------
    */

    /**
     * Malformed Headers: All variations of missing, empty, malformed scheme, or garbage headers return 401.
     */
    public function test_adversarial_malformed_authorization_headers_rejected_401(): void
    {
        // Flush any active session so requests are strictly stateless
        $this->flushSession();
        \Illuminate\Support\Facades\Auth::logout();

        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $tokenData = MobileAccessToken::createTokenForUser($user, 'Header Adversarial Device');
        $validToken = $tokenData['plainTextToken'];

        $headerVariations = [
            'missing_header' => null,
            'empty_string' => '',
            'whitespace_only' => '    ',
            'bearer_with_space_no_token' => 'Bearer ',
            'bearer_no_space_no_token' => 'Bearer',
            'basic_auth_scheme' => 'Basic dXNlcjpzZWNyZXQ=',
            'digest_auth_scheme' => 'Digest username="lucas", realm="sejus"',
            'custom_scheme_token' => 'Token ' . $validToken,
            'custom_scheme_api_key' => 'ApiKey ' . $validToken,
            'missing_bearer_prefix' => $validToken,
            'bearer_with_comma_empty' => 'Bearer , Bearer other_token',
        ];

        foreach ($headerVariations as $label => $headerValue) {
            $req = $headerValue !== null
                ? $this->withHeader('Authorization', $headerValue)
                : $this;

            $resp = $req->postJson('/api/auth/refresh');

            $resp->assertStatus(401, "Header variation [{$label}] did not return 401.")
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }
    }

    /**
     * Case Insensitivity in Authorization Scheme:
     * RFC 6750 / RFC 7235 allow lowercase 'bearer' scheme and case-insensitive header name.
     */
    public function test_adversarial_case_variations_in_authorization_header(): void
    {
        $token1 = $this->getMobileToken();

        // Test with lowercase 'bearer' scheme
        $resp1 = $this->withHeader('Authorization', 'bearer ' . $token1)
            ->postJson('/api/auth/refresh');

        $resp1->assertStatus(200)
            ->assertJson(['status' => 'refreshed']);

        $token2 = $resp1->json('access_token');

        // Test with lowercase header name 'authorization'
        $resp2 = $this->withHeader('authorization', 'Bearer ' . $token2)
            ->postJson('/api/auth/refresh');

        $resp2->assertStatus(200)
            ->assertJson(['status' => 'refreshed']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 6: Audit Logging Correctness & Immutability
    |--------------------------------------------------------------------------
    */

    /**
     * Audit Log Integrity: Successful refresh logs AUTH_TOKEN_REFRESHED with exact metadata.
     * Failed adversarial attempts (replay, tampered, expired, deactivated) do NOT log AUTH_TOKEN_REFRESHED.
     */
    public function test_adversarial_audit_log_accurately_records_auth_token_refreshed(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $token = $this->getMobileToken();

        $auditCountBefore = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();

        // 1. Perform legitimate refresh with custom IP and User-Agent
        $customIp = '187.111.45.92';
        $customUa = 'ConectaEgresso-AdversarialHarness/3.0 (Android 15; Pixel 9)';

        $refreshResp = $this->withServerVariables([
            'REMOTE_ADDR' => $customIp,
            'HTTP_USER_AGENT' => $customUa,
        ])->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $refreshResp->assertStatus(200);

        // Verify audit log increment
        $auditCountAfter = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();
        $this->assertEquals($auditCountBefore + 1, $auditCountAfter);

        // Inspect audit log record details
        $latestLog = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($user->id, $latestLog->user_id);
        $this->assertEquals($customIp, $latestLog->ip_address);
        $this->assertEquals($customUa, $latestLog->user_agent);

        $details = $latestLog->details;
        $this->assertIsArray($details);
        $this->assertEquals($user->id, $details['user_id']);
        $this->assertEquals($user->email, $details['email']);
        $this->assertNotEmpty($details['device_name']);

        // 2. Execute failed adversarial attempts and verify NO audit log is added
        $failedAttempts = [
            fn () => $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/auth/refresh'), // Replay
            fn () => $this->withHeader('Authorization', 'Bearer ce_mob_tampered_deadbeef')->postJson('/api/auth/refresh'), // Tampered
            fn () => $this->postJson('/api/auth/refresh'), // Missing header
        ];

        foreach ($failedAttempts as $idx => $attempt) {
            $resp = $attempt();
            $resp->assertStatus(401);
        }

        $auditCountFinal = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();
        $this->assertEquals($auditCountAfter, $auditCountFinal, 'Failed refresh attempts must NOT log AUTH_TOKEN_REFRESHED.');
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 7: Device Name & Metadata Preservation
    |--------------------------------------------------------------------------
    */

    /**
     * Device Name & PII Masking: The device name from the original token is preserved across refreshes,
     * and masked PII (CPF, telefone) adheres strictly to the LGPD masking format.
     */
    public function test_adversarial_device_name_and_pii_masking_preserved(): void
    {
        $customDevice = 'Xiaomi Redmi Note 13 Pro+ (SEJUS Mobile)';
        $token = $this->getMobileToken(
            login: 'lucas.santos@cidadao.es.gov.br',
            password: 'secret123',
            deviceName: $customDevice
        );

        $tokenHash = hash('sha256', $token);
        $dbTokenBefore = MobileAccessToken::where('token', $tokenHash)->firstOrFail();
        $this->assertEquals($customDevice, $dbTokenBefore->name);

        // Refresh token
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $resp->assertStatus(200);

        $newToken = $resp->json('access_token');
        $newTokenHash = hash('sha256', $newToken);

        // Verify device name was preserved on the newly generated token record
        $dbTokenAfter = MobileAccessToken::where('token', $newTokenHash)->firstOrFail();
        $this->assertEquals($customDevice, $dbTokenAfter->name);

        // Verify LGPD masking: ***.830.456-**
        $userData = $resp->json('user');
        $this->assertNotNull($userData['cpf_masked']);
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $userData['cpf_masked']);
        $this->assertNotNull($userData['telefone_masked']);
        $this->assertMatchesRegularExpression('/^\(\d{2}\)\s9\*{4}-\d{4}$/', $userData['telefone_masked']);

        // Assert no raw CPF or unmasked telefone in response JSON
        $jsonString = $resp->getContent();
        $this->assertStringNotContainsString('19283045678', $jsonString, 'Raw CPF must never leak in refresh response.');
        $this->assertStringNotContainsString('99777-3344', $jsonString, 'Raw telephone must never leak in refresh response.');
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 8: Role Diversity & Cross-Role Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Role Diversity: Verify POST /api/auth/refresh functions securely across all supported roles
     * (egresso, tecnico, gestor, suporte).
     */
    public function test_adversarial_refresh_across_all_roles(): void
    {
        $roleUsers = [
            'egresso' => ['login' => 'lucas.santos@cidadao.es.gov.br', 'role' => 'egresso'],
            'tecnico' => ['login' => 'marcia.oliveira@sejus.es.gov.br', 'role' => 'tecnico'],
            'gestor' => ['login' => 'gestor@sejus.es.gov.br', 'role' => 'gestor'],
            'suporte' => ['login' => 'suporte.agile@sejus.es.gov.br', 'role' => 'suporte'],
        ];

        foreach ($roleUsers as $key => $credentials) {
            $token = $this->getMobileToken($credentials['login'], 'secret123', "Device for {$key}");
            $oldHash = hash('sha256', $token);

            $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh');

            $resp->assertStatus(200, "Role [{$key}] refresh failed.")
                ->assertJson([
                    'status' => 'refreshed',
                    'user' => [
                        'role' => $credentials['role'],
                    ],
                ]);

            $newToken = $resp->json('access_token');
            $newHash = hash('sha256', $newToken);

            // Verify old token removed, new token present
            $this->assertDatabaseMissing('mobile_access_tokens', ['token' => $oldHash]);
            $this->assertDatabaseHas('mobile_access_tokens', ['token' => $newHash]);

            // Verify old token cannot replay
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh')
                ->assertStatus(401);

            // Verify new token works
            $this->withHeader('Authorization', 'Bearer ' . $newToken)
                ->getJson('/api/auth/me')
                ->assertStatus(200);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 9: Race Condition & Rapid Sequential Invalidation Invariant
    |--------------------------------------------------------------------------
    */

    /**
     * Rapid Sequential Refresh Invariant:
     * When 5 rapid refresh requests are sent using the exact same initial token T,
     * strictly 1 request must succeed (HTTP 200) and the remaining 4 must fail (HTTP 401).
     * The database must contain strictly 1 active token and 1 new audit log record.
     */
    public function test_adversarial_rapid_sequential_refresh_on_same_token_allows_exactly_one_success(): void
    {
        $token = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        $auditCountBefore = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();
        $tokensInDbBefore = MobileAccessToken::where('user_id', $user->id)->count();

        $responses = [];
        $statusCodes = [];

        // Send 5 rapid sequential requests with the exact same token
        for ($i = 0; $i < 5; $i++) {
            $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/auth/refresh');

            $statusCodes[] = $resp->status();
            $responses[] = $resp;
        }

        // Exactly one 200 OK, and four 401 UNAUTHENTICATED
        $this->assertEquals([200, 401, 401, 401, 401], $statusCodes, 'Only the first sequential request on token T may succeed.');

        // Verify remaining 4 returned UNAUTHENTICATED error code
        for ($i = 1; $i < 5; $i++) {
            $responses[$i]->assertJson(['code' => 'UNAUTHENTICATED']);
        }

        // Verify audit log incremented by exactly 1
        $auditCountAfter = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')->count();
        $this->assertEquals($auditCountBefore + 1, $auditCountAfter);

        // Verify active token count in database remained constant (1-in, 1-out rotation)
        $tokensInDbAfter = MobileAccessToken::where('user_id', $user->id)->count();
        $this->assertEquals($tokensInDbBefore, $tokensInDbAfter);
    }
}

