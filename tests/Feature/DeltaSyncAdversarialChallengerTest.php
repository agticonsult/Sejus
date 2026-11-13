<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Models\RedeApoio;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

class DeltaSyncAdversarialChallengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to authenticate as mobile egresso user (Lucas Santos) and retrieve Bearer token.
     */
    private function getMobileToken(string $login = 'lucas.santos@cidadao.es.gov.br', string $password = 'secret123'): string
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => 'Challenger Test Harness',
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token, 'Failed to acquire access_token during login helper.');

        return (string) $token;
    }

    /**
     * Data provider for all 5 Delta Sync target endpoints.
     *
     * @return array<string, array{string, string}>
     */
    public static function deltaSyncEndpointsProvider(): array
    {
        return [
            'vagas' => ['/api/vagas', 'vagas'],
            'cursos' => ['/api/cursos', 'cursos'],
            'candidaturas' => ['/api/candidaturas', 'candidaturas'],
            'timeline' => ['/api/prontuarios/1/timeline', 'timeline'],
            'rede_apoio' => ['/api/rede-apoio', 'rede_apoio'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 1: Malformed Timestamp Strings across all 5 Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Test malformed timestamp strings return HTTP 422 with INVALID_SYNC_TIMESTAMP.
     * Specifically tests: null, abc, 2026-99-99, undefined, 2026-02-31, 2026-04-31,
     * non-leap day 2026-02-29, month 13, day 00, month 00, NaN, boolean strings.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_malformed_timestamps_return_422(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        $malformedTimestamps = [
            'null',
            'abc',
            '2026-99-99',
            'undefined',
            '2026-02-31',       // February has max 28/29 days
            '2026-04-31',       // April has 30 days
            '2026-02-29',       // 2026 is NOT a leap year
            '2026-13-01',       // Month 13 does not exist
            '2026-00-15',       // Month 00 does not exist
            '2026-05-00',       // Day 00 does not exist
            'NaN',
            'true',
            'false',
            'not-a-timestamp',
            '2026-10-30T99:99:99Z', // Invalid time components
            'invalid_date_format',
        ];

        foreach ($malformedTimestamps as $malformed) {
            // Test query param 'since'
            $responseSince = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?since=' . urlencode($malformed));

            $responseSince->assertStatus(422)
                ->assertJson([
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ]);
            $this->assertNotEmpty(
                $responseSince->json('error'),
                "Endpoint [{$name}] with since={$malformed} must return a descriptive error message."
            );

            // Test alias query param 'updated_after'
            $responseAlias = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?updated_after=' . urlencode($malformed));

            $responseAlias->assertStatus(422)
                ->assertJson([
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ]);
            $this->assertNotEmpty(
                $responseAlias->json('error'),
                "Endpoint [{$name}] with updated_after={$malformed} must return a descriptive error message."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 2: Extreme Timestamps (Far Future and Unix Epoch Zero)
    |--------------------------------------------------------------------------
    */

    /**
     * Test far future timestamps (e.g. 3000-01-01T00:00:00Z and 2099-12-31T23:59:59Z)
     * return HTTP 200 with data: [] and sync_count: 0.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_far_future_timestamps_return_empty_list_and_zero_sync_count(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        $farFutureTimestamps = [
            '3000-01-01T00:00:00Z',
            '2099-12-31T23:59:59Z',
            '2500-06-15T12:00:00+00:00',
        ];

        foreach ($farFutureTimestamps as $futureDate) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?since=' . urlencode($futureDate));

            $response->assertStatus(200);

            $data = $response->json('data');
            $this->assertIsArray($data, "Endpoint [{$name}] must return data as array.");
            $this->assertSame(
                [],
                $data,
                "Endpoint [{$name}] with future date {$futureDate} must return empty data array."
            );
            $this->assertEquals(
                0,
                $response->json('meta.sync_count'),
                "Endpoint [{$name}] meta.sync_count must be 0 for future date {$futureDate}."
            );
            $this->assertNotEmpty(
                $response->json('meta.server_timestamp'),
                "Endpoint [{$name}] must include meta.server_timestamp."
            );
            $this->assertEquals(
                $futureDate,
                $response->json('meta.since'),
                "Endpoint [{$name}] meta.since must reflect the input timestamp."
            );

            // Also test via updated_after alias
            $responseAlias = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?updated_after=' . urlencode($futureDate));

            $responseAlias->assertStatus(200);
            $this->assertSame([], $responseAlias->json('data'));
            $this->assertEquals(0, $responseAlias->json('meta.sync_count'));
        }
    }

    /**
     * Test Unix epoch 0 (1970-01-01T00:00:00Z) returns all seeded records.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_unix_epoch_zero_returns_all_records(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        // 1. Numeric epoch 0
        $resNumeric = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=0&per_page=100');

        $resNumeric->assertStatus(200);
        $syncCountNumeric = $resNumeric->json('meta.sync_count');
        $this->assertGreaterThan(
            0,
            $syncCountNumeric,
            "Endpoint [{$name}] with since=0 must return all active records."
        );
        $this->assertNotEmpty($resNumeric->json('meta.server_timestamp'));

        // 2. ISO-8601 epoch date string (1970-01-01T00:00:00Z)
        $resIso = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=1970-01-01T00:00:00Z&per_page=100');

        $resIso->assertStatus(200);
        $syncCountIso = $resIso->json('meta.sync_count');
        $this->assertEquals(
            $syncCountNumeric,
            $syncCountIso,
            "Endpoint [{$name}] with ISO epoch must match numeric epoch sync_count."
        );
        $this->assertEquals('1970-01-01T00:00:00Z', $resIso->json('meta.since'));

        // 3. Negative epoch (1969-12-31, e.g. -86400)
        $resNegative = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=-86400&per_page=100');

        $resNegative->assertStatus(200);
        $this->assertEquals(
            $syncCountNumeric,
            $resNegative->json('meta.sync_count'),
            "Endpoint [{$name}] with negative epoch must return all records."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 3: SQL Injection / Command Injection Hostile Strings
    |--------------------------------------------------------------------------
    */

    /**
     * Test SQL injection and command injection payloads in since/updated_after parameters.
     * Must return HTTP 422 with INVALID_SYNC_TIMESTAMP and NOT cause SQL errors or table drops.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_sql_and_command_injection_rejection(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        $initialUsersCount = User::count();
        $initialProntuariosCount = Prontuario::count();
        $initialVagasCount = VagaEmprego::count();

        $injectionPayloads = [
            "' OR 1=1 --",
            "1; DROP TABLE users;",
            "1; DROP TABLE prontuarios; --",
            "' UNION SELECT id, name, email, password FROM users --",
            "2026-10-15' OR 1=1 --",
            "2026-10-15'; DROP TABLE vagas_emprego; --",
            "2026-01-01' OR '1'='1",
            "1' AND (SELECT 1 FROM PG_SLEEP(1))='1",
            "admin'--",
            "'; EXEC xp_cmdshell('dir');--",
            "\"; cat /etc/passwd; \"",
            "$(whoami)",
            "`id`",
            "<script>alert('xss')</script>",
            "{{7*7}}",
            "../../../../etc/passwd",
        ];

        foreach ($injectionPayloads as $payload) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?since=' . urlencode($payload));

            // Must be safely rejected with 422 Unprocessable Entity
            $response->assertStatus(422)
                ->assertJson([
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ]);

            // Ensure no database exception trace or SQL error is leaked
            $responseContent = $response->getContent();
            $this->assertStringNotContainsStringIgnoringCase('syntax error', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('QueryException', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('PostgreSQL', $responseContent);
            $this->assertStringNotContainsStringIgnoringCase('column', $responseContent);

            // Also test via updated_after alias
            $responseAlias = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson($endpoint . '?updated_after=' . urlencode($payload));

            $responseAlias->assertStatus(422)
                ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);
        }

        // Verify database integrity is 100% preserved
        $this->assertEquals($initialUsersCount, User::count(), "Database users table was compromised!");
        $this->assertEquals($initialProntuariosCount, Prontuario::count(), "Database prontuarios table was compromised!");
        $this->assertEquals($initialVagasCount, VagaEmprego::count(), "Database vagas table was compromised!");
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 4: Extreme Pagination Requests (per_page=1000, per_page=-1)
    |--------------------------------------------------------------------------
    */

    /**
     * Test large, negative, and zero pagination boundaries.
     * Expects per_page to be clamped between 1 and 100 without memory exhaustion or crash.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_pagination_boundaries(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        // 1. per_page = 1000 (should be clamped to 100)
        $res1000 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?per_page=1000');

        $res1000->assertStatus(200);
        $perPage1000 = $res1000->json('per_page') ?? $res1000->json('meta.per_page');
        $this->assertNotNull($perPage1000, "Endpoint [{$name}] must report per_page.");
        $this->assertLessThanOrEqual(100, $perPage1000, "Endpoint [{$name}] per_page=1000 must be clamped to <= 100.");
        $data1000 = $res1000->json('data');
        $this->assertLessThanOrEqual(100, count($data1000));

        // 2. per_page = -1 (should be clamped to 1)
        $resNeg = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?per_page=-1');

        $resNeg->assertStatus(200);
        $perPageNeg = $resNeg->json('per_page') ?? $resNeg->json('meta.per_page');
        $this->assertEquals(1, $perPageNeg, "Endpoint [{$name}] per_page=-1 must be clamped to 1.");
        $dataNeg = $resNeg->json('data');
        $this->assertLessThanOrEqual(1, count($dataNeg));

        // 3. per_page = 0 (should be clamped to 1)
        $resZero = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?per_page=0');

        $resZero->assertStatus(200);
        $perPageZero = $resZero->json('per_page') ?? $resZero->json('meta.per_page');
        $this->assertEquals(1, $perPageZero, "Endpoint [{$name}] per_page=0 must be clamped to 1.");

        // 4. per_page = 999999 (should be clamped to 100)
        $resHuge = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?per_page=999999');

        $resHuge->assertStatus(200);
        $perPageHuge = $resHuge->json('per_page') ?? $resHuge->json('meta.per_page');
        $this->assertLessThanOrEqual(100, $perPageHuge, "Endpoint [{$name}] per_page=999999 must be clamped to <= 100.");

        // 5. per_page with non-numeric string 'garbage' (should be converted to int 0 and clamped to 1)
        $resGarbage = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?per_page=garbage');

        $resGarbage->assertStatus(200);
        $perPageGarbage = $resGarbage->json('per_page') ?? $resGarbage->json('meta.per_page');
        $this->assertEquals(1, $perPageGarbage, "Endpoint [{$name}] per_page=garbage must default/clamp to 1.");
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 5: Simultaneous 'since' and 'updated_after' Parameters
    |--------------------------------------------------------------------------
    */

    /**
     * Test simultaneous since and updated_after parameters.
     * Evaluates precedence, conflicts, and coexistence.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_simultaneous_since_and_updated_after(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        // 1. Both present and valid: 'since' takes precedence over 'updated_after'
        // since=1970 (all records) vs updated_after=3000 (0 records)
        $resPrecedence1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=1970-01-01T00:00:00Z&updated_after=3000-01-01T00:00:00Z&per_page=100');

        $resPrecedence1->assertStatus(200);
        $this->assertGreaterThan(
            0,
            $resPrecedence1->json('meta.sync_count'),
            "Endpoint [{$name}]: since=1970 must take precedence over updated_after=future."
        );
        $this->assertEquals('1970-01-01T00:00:00Z', $resPrecedence1->json('meta.since'));

        // 2. Both present: 'since' is future and 'updated_after' is 1970
        // 'since' precedence means empty list is returned
        $resPrecedence2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=3000-01-01T00:00:00Z&updated_after=1970-01-01T00:00:00Z');

        $resPrecedence2->assertStatus(200);
        $this->assertSame(
            [],
            $resPrecedence2->json('data'),
            "Endpoint [{$name}]: since=future must take precedence over updated_after=epoch."
        );
        $this->assertEquals(0, $resPrecedence2->json('meta.sync_count'));

        // 3. Both present: 'since' is invalid, 'updated_after' is valid
        // Because 'since' is present and invalid, request must be rejected with 422
        $resInvalidSince = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=invalid-bad-format&updated_after=2026-10-15T00:00:00Z');

        $resInvalidSince->assertStatus(422)
            ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);

        // 4. Both present and valid with identical values: succeeds with 200 OK
        $validIso = '2026-10-01T00:00:00Z';
        $resIdentical = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since=' . urlencode($validIso) . '&updated_after=' . urlencode($validIso));

        $resIdentical->assertStatus(200);
        $this->assertIsArray($resIdentical->json('data'));
        $this->assertEquals($validIso, $resIdentical->json('meta.since'));

        // 5. 'since' is absent, but 'updated_after' is present and invalid: rejected with 422
        $resOnlyBadAlias = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?updated_after=malformed-alias-timestamp');

        $resOnlyBadAlias->assertStatus(422)
            ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 6: Array / Type Pollution in Query String
    |--------------------------------------------------------------------------
    */

    /**
     * Test parameter pollution when client passes array instead of scalar string (e.g. since[]=...).
     * Must be safely rejected with 422 without PHP fatal error.
     */
    #[DataProvider('deltaSyncEndpointsProvider')]
    public function test_adversarial_array_parameter_pollution(string $endpoint, string $name): void
    {
        $token = $this->getMobileToken();

        // 1. since as array
        $resArraySince = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since[]=2026-10-15');

        $resArraySince->assertStatus(422)
            ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);

        // 2. updated_after as array
        $resArrayAlias = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?updated_after[]=2026-10-15');

        $resArrayAlias->assertStatus(422)
            ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);

        // 3. Associative array in since
        $resAssoc = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($endpoint . '?since[gt]=2026-10-15');

        $resAssoc->assertStatus(422)
            ->assertJson(['code' => 'INVALID_SYNC_TIMESTAMP']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 7: High-Volume Rapid Stress Harness
    |--------------------------------------------------------------------------
    */

    /**
     * Execute 25 sequential rapid requests across all endpoints mixing valid,
     * invalid, and boundary queries to verify process memory stability and response latency.
     */
    public function test_adversarial_high_volume_stress_harness(): void
    {
        $token = $this->getMobileToken();

        $endpoints = [
            '/api/vagas',
            '/api/cursos',
            '/api/candidaturas',
            '/api/prontuarios/1/timeline',
            '/api/rede-apoio',
        ];

        $permutations = [
            ['param' => 'since=0&per_page=50', 'expected_status' => 200],
            ['param' => 'since=3000-01-01T00:00:00Z', 'expected_status' => 200],
            ['param' => 'since=null', 'expected_status' => 422],
            ['param' => 'since=2026-02-31', 'expected_status' => 422],
            ['param' => 'per_page=1000', 'expected_status' => 200],
        ];

        $totalRequests = 0;
        $startTime = microtime(true);

        foreach ($endpoints as $endpoint) {
            foreach ($permutations as $perm) {
                $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                    ->getJson($endpoint . '?' . $perm['param']);

                $response->assertStatus($perm['expected_status']);
                $totalRequests++;
            }
        }

        $elapsed = microtime(true) - $startTime;
        $this->assertEquals(25, $totalRequests, 'Must execute exactly 25 stress requests.');
        $this->assertLessThan(10.0, $elapsed, '25 stress requests must execute in under 10 seconds.');
    }
}
