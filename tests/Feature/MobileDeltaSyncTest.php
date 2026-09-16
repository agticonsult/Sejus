<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use Carbon\Carbon;

class MobileDeltaSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to authenticate as mobile user and retrieve Bearer token.
     */
    private function getMobileToken(string $login = 'lucas.santos@cidadao.es.gov.br', string $password = 'secret123'): string
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token, 'Failed to acquire access_token during login helper.');

        return (string) $token;
    }

    /**
     * R3: GET /api/vagas?since={timestamp} returning only records updated at or after timestamp.
     */
    public function test_delta_sync_returns_records_updated_since_timestamp(): void
    {
        $token = $this->getMobileToken();

        // Mark all existing vagas as updated 10 days ago
        VagaEmprego::query()->update(['updated_at' => Carbon::now()->subDays(10)]);

        // Touch exactly one record to have been updated 1 hour ago
        $recentVaga = VagaEmprego::firstOrFail();
        $recentVaga->updated_at = Carbon::now()->subHour();
        $recentVaga->save();

        // Filter since 2 days ago
        $sinceTimestamp = Carbon::now()->subDays(2)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($sinceTimestamp));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data, 'Delta sync must return only records updated on or after the since parameter.');
        $this->assertEquals($recentVaga->id, $data[0]['id']);

        // Check meta structure
        $this->assertNotNull($response->json('meta'));
        $this->assertEquals(1, $response->json('meta.sync_count'));
    }

    /**
     * R3: GET /api/vagas?updated_after={timestamp} alias behaving identically to since.
     */
    public function test_delta_sync_with_alias_updated_after(): void
    {
        $token = $this->getMobileToken();

        VagaEmprego::query()->update(['updated_at' => Carbon::now()->subDays(10)]);

        $recentVaga = VagaEmprego::firstOrFail();
        $recentVaga->updated_at = Carbon::now()->subMinutes(30);
        $recentVaga->save();

        $timestamp = Carbon::now()->subDays(1)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?updated_after=' . urlencode($timestamp));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($recentVaga->id, $data[0]['id']);
        $this->assertEquals(1, $response->json('meta.sync_count'));
    }

    /**
     * R3: Delta sync with future timestamp returning 200 with data: [] and sync_count: 0.
     */
    public function test_delta_sync_when_no_records_modified_returns_empty_array(): void
    {
        $token = $this->getMobileToken();

        $futureTimestamp = '2099-01-01T00:00:00Z';

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($futureTimestamp));

        $response->assertStatus(200);

        $this->assertSame([], $response->json('data'));
        $this->assertEquals(0, $response->json('meta.sync_count'));
        $this->assertNotNull($response->json('meta.server_timestamp'));
    }

    /**
     * R3: Delta sync with invalid timestamp string returning 422 with INVALID_SYNC_TIMESTAMP.
     */
    public function test_delta_sync_with_invalid_timestamp_returns_422(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=invalid-date-string-not-a-timestamp');

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);

        $this->assertNotEmpty($response->json('error'));

        // Also test the alias updated_after
        $aliasResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?updated_after=bad-format');

        $aliasResponse->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
    }

    /**
     * R3: meta.server_timestamp present in ISO-8601 UTC format.
     */
    public function test_delta_sync_meta_contains_server_timestamp_for_cursor_persistence(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas');

        $response->assertStatus(200);

        $serverTimestamp = $response->json('meta.server_timestamp');
        $this->assertNotEmpty($serverTimestamp, 'meta.server_timestamp must be present.');

        // Verify valid parseable ISO-8601 UTC timestamp
        $parsed = Carbon::parse($serverTimestamp);
        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertLessThanOrEqual(5, abs(Carbon::now()->diffInMinutes($parsed)));
    }

    /**
     * R3: Pagination with since parameter respecting delta count.
     */
    public function test_delta_sync_pagination_respects_since_filter(): void
    {
        $token = $this->getMobileToken();

        // Mark all existing vagas as old
        VagaEmprego::query()->update(['updated_at' => Carbon::now()->subDays(20)]);

        // Update 5 distinct vagas to have recent updated_at
        $cutoff = Carbon::now()->subHours(2);
        $vagas = VagaEmprego::take(5)->get();
        foreach ($vagas as $index => $vaga) {
            $vaga->updated_at = $cutoff->copy()->addMinutes($index * 10);
            $vaga->save();
        }

        // Query page 1 with per_page = 2
        $responsePage1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($cutoff->toIso8601String()) . '&per_page=2&page=1');

        $responsePage1->assertStatus(200);
        $this->assertCount(2, $responsePage1->json('data'));
        $this->assertEquals(1, $responsePage1->json('meta.current_page'));
        $this->assertEquals(2, $responsePage1->json('meta.per_page'));
        $this->assertEquals(5, $responsePage1->json('meta.total'));
        $this->assertEquals(3, $responsePage1->json('meta.last_page'));

        // Query page 2 with per_page = 2
        $responsePage2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($cutoff->toIso8601String()) . '&per_page=2&page=2');

        $responsePage2->assertStatus(200);
        $this->assertCount(2, $responsePage2->json('data'));
        $this->assertEquals(2, $responsePage2->json('meta.current_page'));

        // Ensure page 1 and page 2 items are disjoint
        $idsPage1 = array_column($responsePage1->json('data'), 'id');
        $idsPage2 = array_column($responsePage2->json('data'), 'id');
        $this->assertEmpty(array_intersect($idsPage1, $idsPage2), 'Page 1 and Page 2 must return distinct records.');
    }

    /**
     * R3: Delta sync also functional on cursos endpoint.
     */
    public function test_delta_sync_works_on_cursos_endpoint(): void
    {
        $token = $this->getMobileToken();

        CursoCapacitacao::query()->update(['updated_at' => Carbon::now()->subDays(15)]);

        $recentCurso = CursoCapacitacao::firstOrFail();
        $recentCurso->updated_at = Carbon::now()->subHour();
        $recentCurso->save();

        $since = Carbon::now()->subDays(3)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/cursos?since=' . urlencode($since));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($recentCurso->id, $data[0]['id']);
        $this->assertEquals(1, $response->json('meta.sync_count'));
        $this->assertNotNull($response->json('meta.server_timestamp'));
    }

    /**
     * R3: Delta sync on prontuários endpoint for Egresso.
     */
    public function test_delta_sync_works_on_prontuarios_endpoint_for_egresso(): void
    {
        $token = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123');

        // Older timestamp: record should be returned
        $oldSince = Carbon::now()->subMonths(6)->toIso8601String();
        $resOld = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios?since=' . urlencode($oldSince));

        $resOld->assertStatus(200);
        $this->assertCount(1, $resOld->json('data'));
        $this->assertEquals(1, $resOld->json('meta.sync_count'));
        $this->assertNotNull($resOld->json('meta.server_timestamp'));

        // Future timestamp: record should NOT be returned
        $futureSince = Carbon::now()->addYear()->toIso8601String();
        $resFuture = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios?since=' . urlencode($futureSince));

        $resFuture->assertStatus(200);
        $this->assertCount(0, $resFuture->json('data'));
        $this->assertEquals(0, $resFuture->json('meta.sync_count'));
    }

    /**
     * R3: Delta sync accepts numeric Unix epoch timestamp.
     */
    public function test_delta_sync_with_numeric_epoch_timestamp(): void
    {
        $token = $this->getMobileToken();

        $epoch = Carbon::now()->subDays(5)->timestamp;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . $epoch);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('meta.server_timestamp'));
        $this->assertIsArray($response->json('data'));
    }

    /**
     * R3: Delta sync accepts ISO-8601 timestamp with timezone offset.
     */
    public function test_delta_sync_with_timezone_offset(): void
    {
        $token = $this->getMobileToken();

        $offsetTimestamp = Carbon::now()->subDays(5)->setTimezone('-03:00')->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas?since=' . urlencode($offsetTimestamp));

        $response->assertStatus(200);
        $this->assertNotNull($response->json('meta.server_timestamp'));
        $this->assertIsArray($response->json('data'));
    }
}
