<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ProntuarioTimeline;
use App\Models\RedeApoio;
use Carbon\Carbon;

class MobileDeltaSyncExtendedTest extends TestCase
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

    /*
    |--------------------------------------------------------------------------
    | Candidaturas Delta Sync (/api/candidaturas)
    |--------------------------------------------------------------------------
    */

    public function test_candidaturas_delta_sync_returns_records_updated_since_timestamp(): void
    {
        $token = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        // Mark all timeline events as updated 15 days ago
        ProntuarioTimeline::query()->update(['updated_at' => Carbon::now()->subDays(15)]);

        // Touch the candidatura event for Lucas to 30 minutes ago
        $candidatura = ProntuarioTimeline::whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso'])
            ->whereHas('prontuario', fn($pq) => $pq->where('egresso_id', $user->egresso->id))
            ->firstOrFail();
        $candidatura->updated_at = Carbon::now()->subMinutes(30);
        $candidatura->save();

        $since = Carbon::now()->subHours(2)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?since=' . urlencode($since));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($candidatura->id, $data[0]['id']);

        // Check canonical meta structure
        $this->assertEquals(1, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
        $this->assertEquals($since, $response->json('meta.since'));
    }

    public function test_candidaturas_delta_sync_with_alias_updated_after(): void
    {
        $token = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        ProntuarioTimeline::query()->update(['updated_at' => Carbon::now()->subDays(15)]);

        $candidatura = ProntuarioTimeline::whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso'])
            ->whereHas('prontuario', fn($pq) => $pq->where('egresso_id', $user->egresso->id))
            ->firstOrFail();
        $candidatura->updated_at = Carbon::now()->subMinutes(20);
        $candidatura->save();

        $since = Carbon::now()->subHours(1)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?updated_after=' . urlencode($since));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.sync_count'));
    }

    public function test_candidaturas_delta_sync_future_timestamp_returns_empty_data(): void
    {
        $token = $this->getMobileToken();

        $futureTimestamp = '2099-01-01T00:00:00Z';

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?since=' . urlencode($futureTimestamp));

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertEquals(0, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
    }

    public function test_candidaturas_delta_sync_invalid_timestamp_returns_422(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?since=invalid_not_a_date');

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
        $this->assertNotEmpty($response->json('error'));

        // Alias check
        $aliasResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?updated_after=invalid_date_format');

        $aliasResponse->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
    }

    public function test_candidaturas_delta_sync_formats_epoch_and_tz(): void
    {
        $token = $this->getMobileToken();

        // Epoch seconds
        $epoch = Carbon::now()->subDays(5)->timestamp;
        $resEpoch = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?since=' . $epoch);
        $resEpoch->assertStatus(200);
        $this->assertIsArray($resEpoch->json('data'));
        $this->assertNotNull($resEpoch->json('meta.server_timestamp'));

        // ISO-8601 with TZ offset
        $tzDate = Carbon::now()->subDays(5)->setTimezone('-03:00')->toIso8601String();
        $resTz = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidaturas?since=' . urlencode($tzDate));
        $resTz->assertStatus(200);
        $this->assertIsArray($resTz->json('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | Prontuário Timeline Delta Sync (/api/prontuarios/{id}/timeline)
    |--------------------------------------------------------------------------
    */

    public function test_timeline_delta_sync_returns_records_updated_since_timestamp(): void
    {
        $token = $this->getMobileToken();

        // Mark all timeline events for prontuario 1 as old
        ProntuarioTimeline::where('prontuario_id', 1)->update(['updated_at' => Carbon::now()->subDays(30)]);

        // Touch exactly one event to be recent
        $recentEvent = ProntuarioTimeline::where('prontuario_id', 1)->firstOrFail();
        $recentEvent->updated_at = Carbon::now()->subMinutes(15);
        $recentEvent->save();

        $since = Carbon::now()->subHour()->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?since=' . urlencode($since));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($recentEvent->id, $data[0]['id']);

        // Check canonical meta structure
        $this->assertEquals(1, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
        $this->assertEquals($since, $response->json('meta.since'));
    }

    public function test_timeline_delta_sync_with_alias_updated_after(): void
    {
        $token = $this->getMobileToken();

        ProntuarioTimeline::where('prontuario_id', 1)->update(['updated_at' => Carbon::now()->subDays(30)]);

        $recentEvent = ProntuarioTimeline::where('prontuario_id', 1)->firstOrFail();
        $recentEvent->updated_at = Carbon::now()->subMinutes(10);
        $recentEvent->save();

        $since = Carbon::now()->subHour()->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?updated_after=' . urlencode($since));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.sync_count'));
    }

    public function test_timeline_delta_sync_future_timestamp_returns_empty_data(): void
    {
        $token = $this->getMobileToken();

        $futureTimestamp = '2099-01-01T00:00:00Z';

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?since=' . urlencode($futureTimestamp));

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertEquals(0, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
    }

    public function test_timeline_delta_sync_invalid_timestamp_returns_422(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?since=bad_timestamp_123');

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
        $this->assertNotEmpty($response->json('error'));

        // Alias check
        $aliasResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?updated_after=bad_timestamp_123');

        $aliasResponse->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
    }

    public function test_timeline_delta_sync_formats_epoch_and_tz(): void
    {
        $token = $this->getMobileToken();

        // Epoch seconds
        $epoch = Carbon::now()->subDays(5)->timestamp;
        $resEpoch = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?since=' . $epoch);
        $resEpoch->assertStatus(200);
        $this->assertIsArray($resEpoch->json('data'));
        $this->assertNotNull($resEpoch->json('meta.server_timestamp'));

        // ISO-8601 with TZ offset
        $tzDate = Carbon::now()->subDays(5)->setTimezone('-03:00')->toIso8601String();
        $resTz = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios/1/timeline?since=' . urlencode($tzDate));
        $resTz->assertStatus(200);
        $this->assertIsArray($resTz->json('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | Rede de Apoio Delta Sync (/api/rede-apoio)
    |--------------------------------------------------------------------------
    */

    public function test_rede_apoio_delta_sync_returns_records_updated_since_timestamp(): void
    {
        $token = $this->getMobileToken();

        // Mark all support units as updated 20 days ago
        RedeApoio::query()->update(['updated_at' => Carbon::now()->subDays(20)]);

        // Touch one facility
        $recentUnit = RedeApoio::firstOrFail();
        $recentUnit->updated_at = Carbon::now()->subMinutes(15);
        $recentUnit->save();

        $since = Carbon::now()->subHours(2)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=' . urlencode($since));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($recentUnit->id, $data[0]['id']);

        // Check canonical meta structure
        $this->assertEquals(1, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
        $this->assertEquals($since, $response->json('meta.since'));
    }

    public function test_rede_apoio_delta_sync_with_alias_updated_after(): void
    {
        $token = $this->getMobileToken();

        RedeApoio::query()->update(['updated_at' => Carbon::now()->subDays(20)]);

        $recentUnit = RedeApoio::firstOrFail();
        $recentUnit->updated_at = Carbon::now()->subMinutes(10);
        $recentUnit->save();

        $since = Carbon::now()->subHour()->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?updated_after=' . urlencode($since));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.sync_count'));
    }

    public function test_rede_apoio_delta_sync_future_timestamp_returns_empty_data(): void
    {
        $token = $this->getMobileToken();

        $futureTimestamp = '2099-01-01T00:00:00Z';

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=' . urlencode($futureTimestamp));

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertEquals(0, $response->json('meta.sync_count'));
        $this->assertNotEmpty($response->json('meta.server_timestamp'));
    }

    public function test_rede_apoio_delta_sync_invalid_timestamp_returns_422(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=invalid_sync_format_string');

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
        $this->assertNotEmpty($response->json('error'));

        // Alias check
        $aliasResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?updated_after=invalid_sync_format_string');

        $aliasResponse->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_SYNC_TIMESTAMP',
            ]);
    }

    public function test_rede_apoio_delta_sync_formats_epoch_and_tz(): void
    {
        $token = $this->getMobileToken();

        // Epoch seconds
        $epoch = Carbon::now()->subDays(5)->timestamp;
        $resEpoch = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=' . $epoch);
        $resEpoch->assertStatus(200);
        $this->assertIsArray($resEpoch->json('data'));
        $this->assertNotNull($resEpoch->json('meta.server_timestamp'));

        // ISO-8601 with TZ offset
        $tzDate = Carbon::now()->subDays(5)->setTimezone('-03:00')->toIso8601String();
        $resTz = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=' . urlencode($tzDate));
        $resTz->assertStatus(200);
        $this->assertIsArray($resTz->json('data'));
    }

    public function test_rede_apoio_delta_sync_pagination_respects_since_filter(): void
    {
        $token = $this->getMobileToken();

        RedeApoio::query()->update(['updated_at' => Carbon::now()->subDays(20)]);

        // Touch 4 distinct units
        $units = RedeApoio::take(4)->get();
        $cutoff = Carbon::now()->subHours(2);
        foreach ($units as $index => $unit) {
            $unit->updated_at = $cutoff->copy()->addMinutes($index * 10);
            $unit->save();
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/rede-apoio?since=' . urlencode($cutoff->toIso8601String()) . '&per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.sync_count'));
        $this->assertEquals(4, $response->json('meta.total'));
        $this->assertEquals(1, $response->json('meta.current_page'));
    }
}
