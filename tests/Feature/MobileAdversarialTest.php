<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MobileAccessToken;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\Prontuario;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MobileAdversarialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to authenticate as mobile user and retrieve Bearer token.
     */
    private function getMobileToken(string $login = 'lucas.santos@cidadao.es.gov.br', string $password = 'secret123', string $deviceName = 'Mobile Test Device'): string
    {
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
    | Category 1: Adversarial Auth & Bearer Token Stress
    |--------------------------------------------------------------------------
    */

    /**
     * Test 1: Expired token is rejected with 401 UNAUTHENTICATED.
     */
    public function test_expired_token_is_rejected_with_401(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // Generate a token explicitly expired 10 minutes ago
        $tokenData = MobileAccessToken::createTokenForUser(
            $user,
            'Expired Phone',
            Carbon::now()->subMinutes(10)
        );
        $rawToken = $tokenData['plainTextToken'];

        // 1. Protected endpoint /api/auth/me
        $resMe = $this->withHeader('Authorization', 'Bearer ' . $rawToken)
            ->getJson('/api/auth/me');
        $resMe->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);

        // 2. Protected endpoint /api/carteira
        $resCarteira = $this->withHeader('Authorization', 'Bearer ' . $rawToken)
            ->getJson('/api/carteira');
        $resCarteira->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);

        // 3. Optional auth endpoint /api/vagas with expired token
        $resVagas = $this->withHeader('Authorization', 'Bearer ' . $rawToken)
            ->getJson('/api/vagas');
        $resVagas->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    /**
     * Test 2: Tampered token signatures rejected with 401 UNAUTHENTICATED.
     */
    public function test_tampered_token_signature_rejected_with_401(): void
    {
        $token = $this->getMobileToken();

        // Tamper 1: Flip first character after prefix
        $tampered1 = substr_replace($token, 'x', 7, 1);
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $tampered1)
            ->getJson('/api/auth/me');
        $res1->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Tamper 2: Truncate token
        $tampered2 = substr($token, 0, 20);
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $tampered2)
            ->getJson('/api/auth/me');
        $res2->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Tamper 3: Random hex string
        $tampered3 = 'ce_mob_' . bin2hex(random_bytes(32));
        $res3 = $this->withHeader('Authorization', 'Bearer ' . $tampered3)
            ->getJson('/api/auth/me');
        $res3->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /**
     * Test 3: SQL injection payloads in Bearer header rejected safely without errors.
     */
    public function test_sql_injection_payloads_in_bearer_header(): void
    {
        // First establish an active token to ensure the table contains records
        $validToken = $this->getMobileToken();
        $initialCount = MobileAccessToken::query()->count();
        $this->assertGreaterThan(0, $initialCount);

        // Clear session so each request relies strictly on the Bearer header
        $this->flushSession();

        $payloads = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "'; DROP TABLE mobile_access_tokens; --",
            "Bearer ' UNION SELECT id, password, email FROM users --",
            "admin'--",
            "' OR ''='",
            "1' AND SLEEP(2) --",
            "\" OR \"1\"=\"1",
            "\\x00\\x00\\x00",
        ];

        foreach ($payloads as $sqli) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $sqli)
                ->getJson('/api/auth/me');

            $response->assertStatus(401)
                ->assertJson(['code' => 'UNAUTHENTICATED']);

            // Verify mobile_access_tokens table is intact and unaffected
            $this->assertEquals($initialCount, MobileAccessToken::query()->count());
        }
    }

    /**
     * Test 4: Malformed Authorization header variants.
     */
    public function test_malformed_authorization_headers(): void
    {
        // Obtain valid token for testing bearer format variations
        $validToken = $this->getMobileToken();

        // 4a. Header is "Bearer" with no token or space (clear session to test header-only auth)
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Bearer')
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4b. Header is "Bearer " with whitespace only
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Bearer    ')
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4c. Missing space: "Bearer<token>"
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Bearer' . $validToken)
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4d. Basic auth format instead of Bearer
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Basic ' . base64_encode('user:pass'))
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4e. Token scheme instead of Bearer: "Token <token>"
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'Token ' . $validToken)
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4f. Raw token without scheme prefix
        $this->flushSession();
        $res = $this->withHeader('Authorization', $validToken)
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4g. Empty Authorization header
        $this->flushSession();
        $res = $this->withHeader('Authorization', '')
            ->getJson('/api/auth/me');
        $res->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // 4h. Case-insensitivity check: "bearer <valid_token>" (RFC 6750 lowercase)
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'bearer ' . $validToken)
            ->getJson('/api/auth/me');
        $res->assertStatus(200)
            ->assertJson(['email' => 'lucas.santos@cidadao.es.gov.br']);

        // 4i. Case-insensitivity check: "BEARER <valid_token>" (uppercase)
        $this->flushSession();
        $res = $this->withHeader('Authorization', 'BEARER ' . $validToken)
            ->getJson('/api/auth/me');
        $res->assertStatus(200)
            ->assertJson(['email' => 'lucas.santos@cidadao.es.gov.br']);
    }

    /**
     * Test 5: Extremely long token header (buffer stress / DoS resistance).
     */
    public function test_huge_buffer_in_bearer_header_resilience(): void
    {
        $hugeString = str_repeat('A', 8192);

        $response = $this->withHeader('Authorization', 'Bearer ' . $hugeString)
            ->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    /**
     * Test 6: Concurrent tokens across multiple devices and isolated revocation.
     */
    public function test_concurrent_device_tokens_and_isolated_revocation(): void
    {
        // Device 1: iPhone
        $tokenPhone = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123', 'iPhone 15');
        // Device 2: Android Tablet
        $tokenTablet = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123', 'Galaxy Tab S9');

        $this->assertNotEquals($tokenPhone, $tokenTablet, 'Each device login must issue a distinct token.');

        // Both devices can query /api/auth/me
        $resPhone = $this->withHeader('Authorization', 'Bearer ' . $tokenPhone)->getJson('/api/auth/me');
        $resPhone->assertStatus(200);

        $resTablet = $this->withHeader('Authorization', 'Bearer ' . $tokenTablet)->getJson('/api/auth/me');
        $resTablet->assertStatus(200);

        // Revoke token from Phone via logout
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $tokenPhone)->postJson('/api/auth/logout');
        $logoutRes->assertStatus(200);

        // Phone is now unauthorized
        $resPhoneAfter = $this->withHeader('Authorization', 'Bearer ' . $tokenPhone)->getJson('/api/auth/me');
        $resPhoneAfter->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);

        // Tablet is STILL authorized and untouched!
        $resTabletAfter = $this->withHeader('Authorization', 'Bearer ' . $tokenTablet)->getJson('/api/auth/me');
        $resTabletAfter->assertStatus(200)->assertJson(['email' => 'lucas.santos@cidadao.es.gov.br']);
    }

    /**
     * Test 7: Revoked token re-use prevented across all protected endpoints.
     */
    public function test_revoked_token_reuse_prevented_across_endpoints(): void
    {
        $token = $this->getMobileToken();

        // Confirm active
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/auth/me')->assertStatus(200);

        // Revoke via logout
        $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/auth/logout')->assertStatus(200);

        // Verify re-use attempts on ALL key endpoints are rejected with 401
        $endpoints = [
            ['GET', '/api/auth/me'],
            ['GET', '/api/carteira'],
            ['GET', '/api/carteira/digital'],
            ['GET', '/api/prontuarios'],
            ['POST', '/api/auth/logout'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $token)->json($method, $uri);
            $res->assertStatus(401)
                ->assertJson(['code' => 'UNAUTHENTICATED']);
        }
    }

    /**
     * Test 8: Immediate revocation upon user deactivation.
     */
    public function test_immediate_token_revocation_upon_user_deactivation(): void
    {
        $token = $this->getMobileToken();

        $resBefore = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/auth/me');
        $resBefore->assertStatus(200);

        // Admin deactivates user
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $user->ativo = false;
        $user->save();

        // Next request with active token is immediately blocked with 403 ACCOUNT_DEACTIVATED
        $resAfter = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/auth/me');
        $resAfter->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_DEACTIVATED',
            ]);
    }

    /**
     * Test 9: last_used_at timestamp updates upon successful authentication.
     */
    public function test_token_last_used_at_updates_on_authenticated_request(): void
    {
        $token = $this->getMobileToken();
        $hashed = hash('sha256', $token);

        $record = MobileAccessToken::where('token', $hashed)->firstOrFail();
        $this->assertNull($record->last_used_at);

        // Make an authenticated request
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/auth/me')->assertStatus(200);

        $record->refresh();
        $this->assertNotNull($record->last_used_at);
        $this->assertInstanceOf(Carbon::class, $record->last_used_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Category 2: Delta Sync Boundary Stress & Edge Cases
    |--------------------------------------------------------------------------
    */

    /**
     * Test 10: Far-future timestamps (Year 3000, 9999).
     */
    public function test_delta_sync_far_future_timestamps(): void
    {
        $token = $this->getMobileToken();

        $futureDates = [
            '3000-01-01T00:00:00Z',
            '3000-12-31T23:59:59Z',
            '9999-12-31T23:59:59Z',
        ];

        foreach ($futureDates as $date) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($date));

            $response->assertStatus(200);
            $this->assertSame([], $response->json('data'));
            $this->assertEquals(0, $response->json('meta.sync_count'));
            $this->assertEquals($date, $response->json('meta.since'));
            $this->assertNotNull($response->json('meta.server_timestamp'));
        }
    }

    /**
     * Test 11: Far-past timestamps (1970-01-01, 1900-01-01).
     */
    public function test_delta_sync_far_past_timestamps_returns_all_records(): void
    {
        $token = $this->getMobileToken();

        $pastDates = [
            '1970-01-01T00:00:00Z',
            '1900-01-01T00:00:00Z',
        ];

        $totalSeeded = VagaEmprego::query()->count();
        $this->assertGreaterThan(0, $totalSeeded);

        foreach ($pastDates as $date) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($date) . '&per_page=100');

            $response->assertStatus(200);
            $this->assertEquals($totalSeeded, $response->json('meta.total'));
            $this->assertEquals($totalSeeded, $response->json('meta.sync_count'));
        }
    }

    /**
     * Test 12: Leap year validation: valid leap days pass, malformed/non-date strings rejected with 422.
     */
    public function test_delta_sync_leap_year_boundaries(): void
    {
        $token = $this->getMobileToken();

        // Valid leap days: 2024-02-29 and 2028-02-29
        $validLeapDays = [
            '2024-02-29T12:00:00Z',
            '2028-02-29T23:59:59Z',
        ];

        foreach ($validLeapDays as $validDate) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($validDate));

            $response->assertStatus(200);
            $this->assertNotNull($response->json('meta.server_timestamp'));
        }

        // Invalid timestamp formats (malformed syntax, non-numeric garbage)
        $invalidDates = [
            'not-a-timestamp',
            '2026-99-99',
            'invalid-format-999',
            'date_2026_01_01',
            '2026-00-00T00:00:00Z',
        ];

        foreach ($invalidDates as $badDate) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($badDate));

            $response->assertStatus(422)
                ->assertJson([
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ]);
        }
    }

    /**
     * Test 13: Microsecond and millisecond precision in timestamps.
     */
    public function test_delta_sync_fractional_second_precision(): void
    {
        $token = $this->getMobileToken();

        $preciseDates = [
            '2026-09-15T12:00:00.123456Z',
            '2026-09-15T12:00:00.999999Z',
            '2026-09-15T12:00:00.500+00:00',
            '2026-09-15T09:00:00.123-03:00',
        ];

        foreach ($preciseDates as $pDate) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($pDate));

            $response->assertStatus(200);
            $this->assertNotNull($response->json('meta.server_timestamp'));
        }
    }

    /**
     * Test 14: Numeric epoch 0 and negative epoch timestamps.
     */
    public function test_delta_sync_numeric_zero_and_negative_epoch(): void
    {
        $token = $this->getMobileToken();

        // Epoch 0: 1970-01-01 00:00:00 UTC
        $resZero = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=0&per_page=100');
        $resZero->assertStatus(200);
        $this->assertGreaterThan(0, $resZero->json('meta.sync_count'));

        // Negative epoch: -86400 (1969-12-31)
        $resNegative = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=-86400&per_page=100');
        $resNegative->assertStatus(200);
        $this->assertGreaterThan(0, $resNegative->json('meta.sync_count'));
    }

    /**
     * Test 15: SQL Injection and hostile strings in delta sync since parameter.
     */
    public function test_delta_sync_sql_injection_rejection(): void
    {
        $token = $this->getMobileToken();

        $attacks = [
            "2026-01-01' OR '1'='1",
            "2026-01-01'; DROP TABLE vagas_emprego; --",
            "1 UNION SELECT * FROM users",
            "../../etc/passwd",
            "<script>alert(1)</script>",
            "null",
            "undefined",
            "NaN",
            "true",
            "false",
        ];

        foreach ($attacks as $attack) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/vagas?since=' . urlencode($attack));

            $response->assertStatus(422)
                ->assertJson([
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ]);
        }
    }

    /**
     * Test 16: Client cursor adherence and delta continuity.
     */
    public function test_delta_sync_cursor_continuity_and_upsert_simulation(): void
    {
        $token = $this->getMobileToken();

        // Mark all existing records as updated 10 minutes ago
        VagaEmprego::query()->update(['updated_at' => Carbon::now()->subMinutes(10)]);

        // Initial sync: fetch all records and capture server cursor
        $initialRes = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/vagas');
        $initialRes->assertStatus(200);

        $serverCursor = $initialRes->json('meta.server_timestamp');
        $this->assertNotEmpty($serverCursor);

        // Mobile device performs subsequent sync with serverCursor: should receive 0 records
        $deltaRes1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($serverCursor));
        $deltaRes1->assertStatus(200);
        $this->assertEquals(0, $deltaRes1->json('meta.sync_count'));

        // Advance 1 second, then backend updates exactly 1 vacancy
        sleep(1);
        $vaga = VagaEmprego::firstOrFail();
        $vaga->titulo = 'Vaga Atualizada em Tempo Real';
        $vaga->updated_at = Carbon::now();
        $vaga->save();

        // Advance 1 second, then mobile syncs with serverCursor: receives exactly the 1 modified record
        sleep(1);
        $deltaRes2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($serverCursor));
        $deltaRes2->assertStatus(200);
        $this->assertEquals(1, $deltaRes2->json('meta.sync_count'));
        $this->assertEquals($vaga->id, $deltaRes2->json('data.0.id'));
        $this->assertEquals('Vaga Atualizada em Tempo Real', $deltaRes2->json('data.0.titulo'));

        // Mobile advances cursor to new server_timestamp
        $newCursor = $deltaRes2->json('meta.server_timestamp');

        // Next sync with newCursor: again 0 records (since vaga was updated before newCursor)
        $deltaRes3 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($newCursor));
        $deltaRes3->assertStatus(200);
        $this->assertEquals(0, $deltaRes3->json('meta.sync_count'));
    }

    /**
     * Test 17: Delta sync meta structure consistency across vagas, cursos, and prontuarios.
     */
    public function test_delta_sync_meta_structure_consistency(): void
    {
        $token = $this->getMobileToken();
        $since = Carbon::now()->subDays(30)->toIso8601String();

        $endpoints = [
            '/api/vagas?since=' . urlencode($since),
            '/api/cursos?since=' . urlencode($since),
            '/api/prontuarios?since=' . urlencode($since),
        ];

        foreach ($endpoints as $url) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson($url);
            $response->assertStatus(200);

            $meta = $response->json('meta');
            $this->assertIsArray($meta);
            $this->assertArrayHasKey('server_timestamp', $meta);
            $this->assertArrayHasKey('sync_count', $meta);
            $this->assertArrayHasKey('since', $meta);
            $this->assertArrayHasKey('total', $meta);
            $this->assertArrayHasKey('current_page', $meta);
            $this->assertArrayHasKey('per_page', $meta);
            $this->assertArrayHasKey('last_page', $meta);

            $this->assertEquals($since, $meta['since']);
            $this->assertIsInt($meta['sync_count']);
            $this->assertIsInt($meta['total']);

            // Validate that server_timestamp is a valid ISO-8601 date
            $parsed = Carbon::parse($meta['server_timestamp']);
            $this->assertInstanceOf(Carbon::class, $parsed);
        }
    }
}
