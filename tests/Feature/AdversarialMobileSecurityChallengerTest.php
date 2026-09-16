<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\MobileAccessToken;
use Illuminate\Support\Facades\DB;

/**
 * Adversarial Security & Privacy Test Suite (Challenger 2)
 *
 * Rigorous empirical validation of:
 * 1. RBAC Boundaries & Cross-Egresso Access Prevention
 * 2. LGPD CPF / Sensitive Data Masking Compliance
 * 3. Database Token Hash Storage & Revocation Security
 */
class AdversarialMobileSecurityChallengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to login via mobile API and return plaintext token and user array.
     */
    private function loginMobile(string $login, string $password = 'secret123'): array
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => 'Adversarial Tester Mobile',
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token, 'Failed to acquire access_token during helper login.');

        return [
            'token' => $token,
            'user' => $response->json('user'),
        ];
    }

    // =========================================================================
    // 1. RBAC BOUNDARIES & CROSS-EGRESSO ACCESS PREVENTION
    // =========================================================================

    /**
     * Attack Scenario: Egresso 1 (Lucas) attempts to access Egresso 2's (Roberto) carteira.
     * Expected: Carteira endpoint is strictly bound to authenticated user; parameter tampering
     * (e.g. ?egresso_id=2, ?id=2, ?user_id=4) is completely ignored and only Lucas's data is returned.
     */
    public function test_cross_egresso_carteira_tampering_blocked(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $robertoAuth = $this->loginMobile('roberto.fonseca@cidadao.es.gov.br');

        // Lucas requests carteira without params
        $resLucas = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/carteira');
        $resLucas->assertStatus(200);
        $this->assertEquals('LUCAS SANTOS', $resLucas->json('data.nome'));
        $this->assertEquals(1, $resLucas->json('data.id'));

        // Lucas attempts query parameter injection to view Roberto's carteira
        $resTampered1 = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/carteira?egresso_id=2&id=2&user_id=4');
        $resTampered1->assertStatus(200);
        $this->assertEquals('LUCAS SANTOS', $resTampered1->json('data.nome'));
        $this->assertEquals(1, $resTampered1->json('data.id'));
        $this->assertNotEquals('ROBERTO FONSECA DA SILVA', $resTampered1->json('data.nome'));

        // Roberto requests carteira
        $resRoberto = $this->withHeader('Authorization', 'Bearer ' . $robertoAuth['token'])
            ->getJson('/api/carteira');
        $resRoberto->assertStatus(200);
        $this->assertEquals('ROBERTO FONSECA DA SILVA', $resRoberto->json('data.nome'));
        $this->assertEquals(2, $resRoberto->json('data.id'));

        // Roberto attempts query parameter injection to view Lucas's carteira
        $resTampered2 = $this->withHeader('Authorization', 'Bearer ' . $robertoAuth['token'])
            ->getJson('/api/carteira?egresso_id=1&id=1&user_id=3');
        $resTampered2->assertStatus(200);
        $this->assertEquals('ROBERTO FONSECA DA SILVA', $resTampered2->json('data.nome'));
        $this->assertEquals(2, $resTampered2->json('data.id'));
        $this->assertNotEquals('LUCAS SANTOS', $resTampered2->json('data.nome'));
    }

    /**
     * Attack Scenario: Non-egresso roles (Gestor, Técnico, Suporte) attempt to query /api/carteira.
     * Expected: Returns 404 with code EGRESSO_NOT_FOUND.
     */
    public function test_non_egresso_roles_receive_404_on_carteira(): void
    {
        // 1. Gestor
        $gestorAuth = $this->loginMobile('gestor@sejus.es.gov.br');
        $resGestor = $this->withHeader('Authorization', 'Bearer ' . $gestorAuth['token'])
            ->getJson('/api/carteira');
        $resGestor->assertStatus(404)
            ->assertJson([
                'code' => 'EGRESSO_NOT_FOUND',
            ]);

        // 2. Técnico
        $tecnicoAuth = $this->loginMobile('marcia.oliveira@sejus.es.gov.br');
        $resTecnico = $this->withHeader('Authorization', 'Bearer ' . $tecnicoAuth['token'])
            ->getJson('/api/carteira');
        $resTecnico->assertStatus(404)
            ->assertJson([
                'code' => 'EGRESSO_NOT_FOUND',
            ]);

        // 3. Suporte
        $suporteAuth = $this->loginMobile('suporte.agile@sejus.es.gov.br');
        $resSuporte = $this->withHeader('Authorization', 'Bearer ' . $suporteAuth['token'])
            ->getJson('/api/carteira');
        $resSuporte->assertStatus(404)
            ->assertJson([
                'code' => 'EGRESSO_NOT_FOUND',
            ]);

        // Check alias /api/carteira/digital
        $resAlias = $this->withHeader('Authorization', 'Bearer ' . $gestorAuth['token'])
            ->getJson('/api/carteira/digital');
        $resAlias->assertStatus(404)
            ->assertJson(['code' => 'EGRESSO_NOT_FOUND']);
    }

    /**
     * Attack Scenario: Unauthenticated request to /api/carteira.
     * Expected: Returns 401 UNAUTHENTICATED.
     */
    public function test_unauthenticated_carteira_receives_401(): void
    {
        $response = $this->getJson('/api/carteira');
        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);

        $responseAlias = $this->getJson('/api/carteira/digital');
        $responseAlias->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    /**
     * Attack Scenario: Egresso lists /api/prontuarios.
     * Expected: Returns strictly an array of count 1 containing only their own record.
     * Other egressos' prontuarios must NEVER appear.
     */
    public function test_egresso_prontuario_listing_strictly_isolated(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $robertoAuth = $this->loginMobile('roberto.fonseca@cidadao.es.gov.br');

        // Lucas queries listing
        $resLucas = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios');
        $resLucas->assertStatus(200);

        $dataLucas = $resLucas->json('data');
        $this->assertCount(1, $dataLucas);
        $this->assertEquals('PRT-2026-000001', $dataLucas[0]['numero_prontuario']);
        $this->assertEquals(1, $dataLucas[0]['egresso']['id']);
        $this->assertEquals('Lucas Santos', $dataLucas[0]['egresso']['nome_completo']);

        // Roberto queries listing
        $resRoberto = $this->withHeader('Authorization', 'Bearer ' . $robertoAuth['token'])
            ->getJson('/api/prontuarios');
        $resRoberto->assertStatus(200);

        $dataRoberto = $resRoberto->json('data');
        $this->assertCount(1, $dataRoberto);
        $this->assertEquals('PRT-2026-000002', $dataRoberto[0]['numero_prontuario']);
        $this->assertEquals(2, $dataRoberto[0]['egresso']['id']);
        $this->assertEquals('Roberto Fonseca da Silva', $dataRoberto[0]['egresso']['nome_completo']);
    }

    /**
     * Attack Scenario: Egresso 1 (Lucas) attempts direct horizontal privilege escalation
     * by querying /api/prontuarios/{id} for Egresso 2's record (id = 2).
     * Expected: Returns 403 FORBIDDEN.
     */
    public function test_cross_egresso_prontuario_direct_access_blocked(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $robertoAuth = $this->loginMobile('roberto.fonseca@cidadao.es.gov.br');

        // Lucas accesses his own prontuario (id = 1) -> 200 OK
        $resSelf = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/1');
        $resSelf->assertStatus(200);
        $this->assertEquals('PRT-2026-000001', $resSelf->json('data.numero_prontuario'));

        // Lucas attacks Roberto's prontuario (id = 2) -> 403 FORBIDDEN
        $resAttack1 = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/2');
        $resAttack1->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        // Roberto attacks Lucas's prontuario (id = 1) -> 403 FORBIDDEN
        $resAttack2 = $this->withHeader('Authorization', 'Bearer ' . $robertoAuth['token'])
            ->getJson('/api/prontuarios/1');
        $resAttack2->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    /**
     * Attack Scenario: Egresso 1 (Lucas) queries /api/prontuarios/{numero_prontuario}
     * using Roberto's record number ('PRT-2026-000002').
     * Expected: Returns 403 FORBIDDEN.
     */
    public function test_cross_egresso_prontuario_by_record_number_blocked(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');

        $response = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/PRT-2026-000002');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    /**
     * Attack Scenario: Egresso 1 (Lucas) queries timeline events for Roberto's prontuário.
     * Expected: Returns 403 FORBIDDEN on /api/prontuarios/2/timeline.
     */
    public function test_cross_egresso_prontuario_timeline_access_blocked(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');

        // Lucas accessing Roberto's timeline by ID
        $resById = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/2/timeline');
        $resById->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);

        // Lucas accessing Roberto's timeline by record number
        $resByNum = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/PRT-2026-000002/timeline');
        $resByNum->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);

        // Lucas accessing his own timeline -> 200 OK
        $resOwn = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios/1/timeline');
        $resOwn->assertStatus(200);
        $this->assertEquals(1, $resOwn->json('prontuario_id'));
    }

    /**
     * Attack Scenario: Egresso attempts write operations (create, update, delete) on prontuários.
     * Expected: All write attempts return 403 FORBIDDEN.
     */
    public function test_egresso_cannot_create_update_or_delete_prontuario(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');

        // 1. Create attempt
        $resStore = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->postJson('/api/prontuarios', [
                'egresso_id' => 1,
                'situacao' => 'ativo',
                'resumo_diagnostico' => 'Tentativa maliciosa de criar prontuario',
            ]);
        $resStore->assertStatus(403);

        // 2. Update own prontuario
        $resUpdateOwn = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->putJson('/api/prontuarios/1', [
                'resumo_diagnostico' => 'Tentativa maliciosa de alterar diagnostico',
            ]);
        $resUpdateOwn->assertStatus(403);

        // 3. Update another egresso's prontuario
        $resUpdateOther = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->putJson('/api/prontuarios/2', [
                'resumo_diagnostico' => 'Tentativa de alterar outro prontuario',
            ]);
        $resUpdateOther->assertStatus(403);

        // 4. Delete own prontuario
        $resDeleteOwn = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->deleteJson('/api/prontuarios/1');
        $resDeleteOwn->assertStatus(403);

        // 5. Delete another egresso's prontuario
        $resDeleteOther = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->deleteJson('/api/prontuarios/2');
        $resDeleteOther->assertStatus(403);
    }

    /**
     * Attack Scenario: Egresso attempts to add timeline events or clinical evolutions.
     * Expected: Returns 403 FORBIDDEN_ROLE_RESTRICTION.
     */
    public function test_egresso_cannot_write_timeline_or_evolucao(): void
    {
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');

        // 1. Timeline store
        $resTimeline = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->postJson('/api/prontuarios/1/timeline', [
                'tipo_evento' => 'apoio_psicossocial',
                'titulo' => 'Auto-atendimento simulado',
                'descricao' => 'Injeção de evento falso',
            ]);
        $resTimeline->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN_ROLE_RESTRICTION']);

        // 2. Evolucao store
        $resEvolucao = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->postJson('/api/prontuarios/1/evolucao', [
                'descricao' => 'Falsificação de evolução clínica',
            ]);
        $resEvolucao->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN_ROLE_RESTRICTION']);
    }

    /**
     * Verification: Staff roles (Gestor, Técnico) can list and inspect all prontuários.
     */
    public function test_gestor_and_tecnico_can_access_all_prontuarios(): void
    {
        $tecnicoAuth = $this->loginMobile('marcia.oliveira@sejus.es.gov.br');

        $response = $this->withHeader('Authorization', 'Bearer ' . $tecnicoAuth['token'])
            ->getJson('/api/prontuarios');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));

        $numbers = array_column($data, 'numero_prontuario');
        $this->assertContains('PRT-2026-000001', $numbers);
        $this->assertContains('PRT-2026-000002', $numbers);
    }

    // =========================================================================
    // 2. LGPD & DATA LEAKAGE ATTACK VERIFICATION
    // =========================================================================

    /**
     * Attack Scenario: Inspect login and profile endpoints for raw CPF leakage.
     * Expected: Only masked CPF (e.g. 192.***.***-78) is returned. Plaintext CPF
     * (19283045678 or 192.830.456-78) NEVER appears in the raw response body.
     */
    public function test_lgpd_raw_cpf_never_leaks_in_auth_login_me(): void
    {
        $rawCpfLucas = '19283045678';
        $formattedCpfLucas = '192.830.456-78';
        $expectedMasked = app(\App\Services\LgpdSecurityService::class)->maskCpf($rawCpfLucas);

        // 1. Login with email
        $resLogin = $this->postJson('/api/auth/login', [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'secret123',
        ]);
        $resLogin->assertStatus(200);
        $bodyLogin = $resLogin->getContent();

        $this->assertStringNotContainsString($rawCpfLucas, $bodyLogin, 'Raw CPF must never leak in login response.');
        $this->assertStringNotContainsString($formattedCpfLucas, $bodyLogin, 'Unmasked formatted CPF must never leak in login response.');
        $this->assertEquals($expectedMasked, $resLogin->json('user.cpf_masked'));
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $resLogin->json('user.cpf_masked'));

        $token = $resLogin->json('access_token');

        // 2. GET /api/auth/me
        $resMe = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $resMe->assertStatus(200);
        $bodyMe = $resMe->getContent();

        $this->assertStringNotContainsString($rawCpfLucas, $bodyMe, 'Raw CPF must never leak in /api/auth/me response.');
        $this->assertStringNotContainsString($formattedCpfLucas, $bodyMe, 'Unmasked formatted CPF must never leak in /api/auth/me response.');
        $this->assertEquals($expectedMasked, $resMe->json('cpf_masked'));
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $resMe->json('cpf_masked'));
    }

    /**
     * Attack Scenario: Inspect /api/carteira payload and cryptographic token envelope.
     * Expected: The JSON payload, QR code URI, and internal signed token contain
     * only the masked CPF, never the raw plaintext CPF.
     */
    public function test_lgpd_raw_cpf_never_leaks_in_carteira_and_qr_token(): void
    {
        $rawCpfLucas = '19283045678';
        $formattedCpfLucas = '192.830.456-78';
        $expectedMasked = app(\App\Services\LgpdSecurityService::class)->maskCpf($rawCpfLucas);

        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');

        $response = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/carteira');
        $response->assertStatus(200);

        $body = $response->getContent();
        $this->assertStringNotContainsString($rawCpfLucas, $body, 'Raw CPF leaked in /api/carteira response body!');
        $this->assertStringNotContainsString($formattedCpfLucas, $body, 'Unmasked formatted CPF leaked in /api/carteira response body!');
        $this->assertEquals($expectedMasked, $response->json('data.cpf_masked'));
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $response->json('data.cpf_masked'));

        // Inspect the signed token inside the response
        $walletToken = $response->json('data.token');
        $this->assertNotEmpty($walletToken);

        // Decode URL-safe Base64 token envelope
        $padded = str_pad(strtr($walletToken, '-_', '+/'), strlen($walletToken) % 4 === 0 ? strlen($walletToken) : strlen($walletToken) + (4 - strlen($walletToken) % 4), '=', STR_PAD_RIGHT);
        $decodedJson = base64_decode($padded, true);
        $this->assertNotFalse($decodedJson, 'Token envelope should be valid base64 JSON.');

        $this->assertStringNotContainsString($rawCpfLucas, $decodedJson, 'Cryptographic token payload leaked raw CPF!');
        $this->assertStringNotContainsString($formattedCpfLucas, $decodedJson, 'Cryptographic token payload leaked formatted CPF!');

        $envelope = json_decode($decodedJson, true);
        $this->assertArrayHasKey('p', $envelope);
        $this->assertEquals($expectedMasked, $envelope['p']['cpf_masked']);
    }

    /**
     * Attack Scenario: Inspect public verification endpoint /api/validar-carteira/{token}.
     * Expected: Payload returned to the public scanner contains ONLY masked CPF.
     */
    public function test_lgpd_raw_cpf_never_leaks_in_public_qr_validation(): void
    {
        $rawCpfLucas = '19283045678';
        $formattedCpfLucas = '192.830.456-78';
        $expectedMasked = app(\App\Services\LgpdSecurityService::class)->maskCpf($rawCpfLucas);

        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $resCarteira = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/carteira');
        $walletToken = $resCarteira->json('data.token');

        $resVerify = $this->getJson('/api/validar-carteira/' . $walletToken);
        $resVerify->assertStatus(200);

        $body = $resVerify->getContent();
        $this->assertStringNotContainsString($rawCpfLucas, $body, 'Public QR verification leaked raw CPF!');
        $this->assertStringNotContainsString($formattedCpfLucas, $body, 'Public QR verification leaked unmasked formatted CPF!');
        $this->assertEquals($expectedMasked, $resVerify->json('payload.cpf_masked'));
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $resVerify->json('payload.cpf_masked'));
    }

    /**
     * Attack Scenario: Inspect /api/prontuarios responses for raw CPF leakage.
     * Expected: All egresso representations in prontuário payloads have masked CPFs.
     */
    public function test_lgpd_raw_cpf_never_leaks_in_prontuario_payloads(): void
    {
        $rawCpfLucas = '19283045678';
        $rawCpfRoberto = '48291037492';
        $expectedMaskedLucas = app(\App\Services\LgpdSecurityService::class)->maskCpf($rawCpfLucas);

        // 1. As Egresso (Lucas)
        $lucasAuth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $resLucas = $this->withHeader('Authorization', 'Bearer ' . $lucasAuth['token'])
            ->getJson('/api/prontuarios');
        $resLucas->assertStatus(200);

        $bodyLucas = $resLucas->getContent();
        $this->assertStringNotContainsString($rawCpfLucas, $bodyLucas);
        $this->assertStringNotContainsString($rawCpfRoberto, $bodyLucas);
        $this->assertEquals($expectedMaskedLucas, $resLucas->json('data.0.egresso.cpf_masked'));
        $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $resLucas->json('data.0.egresso.cpf_masked'));

        // 2. As Técnico (listing all)
        $tecnicoAuth = $this->loginMobile('marcia.oliveira@sejus.es.gov.br');
        $resTecnico = $this->withHeader('Authorization', 'Bearer ' . $tecnicoAuth['token'])
            ->getJson('/api/prontuarios');
        $resTecnico->assertStatus(200);

        $bodyTecnico = $resTecnico->getContent();
        $this->assertStringNotContainsString($rawCpfLucas, $bodyTecnico);
        $this->assertStringNotContainsString($rawCpfRoberto, $bodyTecnico);

        foreach ($resTecnico->json('data') as $item) {
            if (!empty($item['egresso']['cpf_masked'])) {
                $this->assertStringContainsString('***', $item['egresso']['cpf_masked']);
                $this->assertMatchesRegularExpression('/^\*{3}\.\d{3}\.\d{3}-\*{2}$/', $item['egresso']['cpf_masked']);
            }
        }
    }

    // =========================================================================
    // 3. DATABASE TOKEN HASH STORAGE & REVOCATION SECURITY
    // =========================================================================

    /**
     * Attack Scenario: Direct database inspection of the mobile_access_tokens table.
     * Expected:
     * 1. Plaintext tokens (ce_mob_...) are NEVER stored in the database.
     * 2. All stored tokens are strictly 64-character SHA-256 hex hashes.
     * 3. Plaintext token cannot be looked up directly in the token column.
     */
    public function test_database_never_stores_plaintext_mobile_tokens(): void
    {
        $auth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $plainTextToken = $auth['token'];

        $this->assertStringStartsWith('ce_mob_', $plainTextToken);

        // Check if plainTextToken exists in DB column
        $directMatch = DB::table('mobile_access_tokens')
            ->where('token', $plainTextToken)
            ->first();
        $this->assertNull($directMatch, 'Security Violation: Plaintext mobile token found in database!');

        // Check for any tokens starting with 'ce_mob_' in the database
        $leakedTokensCount = DB::table('mobile_access_tokens')
            ->where('token', 'LIKE', 'ce_mob_%')
            ->count();
        $this->assertEquals(0, $leakedTokensCount, 'Security Violation: Unhashed tokens stored in database!');

        // Verify that all tokens in the database are exactly 64 characters (SHA-256 length)
        $allTokens = DB::table('mobile_access_tokens')->pluck('token');
        $this->assertNotEmpty($allTokens);
        foreach ($allTokens as $dbToken) {
            $this->assertEquals(64, strlen($dbToken), 'Stored token is not a 64-char SHA-256 hash.');
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $dbToken, 'Stored token is not valid hex SHA-256.');
        }
    }

    /**
     * Attack Scenario: Verify that computing hash('sha256', $plainTextToken) matches the DB row.
     */
    public function test_database_token_hash_matches_sha256(): void
    {
        $auth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $plainTextToken = $auth['token'];
        $expectedHash = hash('sha256', $plainTextToken);

        $record = DB::table('mobile_access_tokens')
            ->where('token', $expectedHash)
            ->first();

        $this->assertNotNull($record, 'Expected SHA-256 hash not found in database.');
        $this->assertEquals($auth['user']['id'], $record->user_id);
    }

    /**
     * Attack Scenario: Logout revocation attack.
     * Expected:
     * 1. Token record is deleted immediately from the database upon logout.
     * 2. Subsequent requests with that token return 401 UNAUTHENTICATED.
     * 3. A second logout attempt with the same token returns 401 UNAUTHENTICATED.
     */
    public function test_mobile_logout_revokes_token_from_database_immediately(): void
    {
        $auth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $token = $auth['token'];
        $hashed = hash('sha256', $token);

        // Verify token is in DB
        $this->assertDatabaseHas('mobile_access_tokens', ['token' => $hashed]);

        // Access protected endpoint
        $resBefore = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $resBefore->assertStatus(200);

        // Logout
        $resLogout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');
        $resLogout->assertStatus(200)
            ->assertJson(['status' => 'logged_out']);

        // Verify token is REMOVED from DB
        $this->assertDatabaseMissing('mobile_access_tokens', ['token' => $hashed]);

        // Attempting to access protected endpoints with revoked token
        $resAfterMe = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $resAfterMe->assertStatus(401);

        $resAfterCarteira = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/carteira');
        $resAfterCarteira->assertStatus(401);

        $resAfterProntuarios = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios');
        $resAfterProntuarios->assertStatus(401);

        // Attempting to logout again with already revoked token
        $resSecondLogout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');
        $resSecondLogout->assertStatus(401);
    }

    /**
     * Attack Scenario: Bearer token tampering, expiration, and account deactivation.
     * Expected:
     * 1. Tampered token -> 401 UNAUTHENTICATED
     * 2. Expired token in database -> 401 UNAUTHENTICATED
     * 3. Inactive account -> 403 ACCOUNT_DEACTIVATED
     */
    public function test_tampered_expired_and_deactivated_tokens_are_rejected(): void
    {
        $auth = $this->loginMobile('lucas.santos@cidadao.es.gov.br');
        $token = $auth['token'];
        $hashed = hash('sha256', $token);

        // 1. Tampered token: change last char
        $tamperedToken = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');
        $resTampered = $this->withHeader('Authorization', 'Bearer ' . $tamperedToken)
            ->getJson('/api/auth/me');
        $resTampered->assertStatus(401);

        // 2. Expired token: force DB expiration in the past
        DB::table('mobile_access_tokens')
            ->where('token', $hashed)
            ->update(['expires_at' => now()->subDay()]);

        $resExpired = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $resExpired->assertStatus(401);

        // 3. Deactivated account: restore expiration, set user->ativo = false
        DB::table('mobile_access_tokens')
            ->where('token', $hashed)
            ->update(['expires_at' => now()->addDays(30)]);

        User::where('email', 'lucas.santos@cidadao.es.gov.br')->update(['ativo' => false]);

        $resDeactivated = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $resDeactivated->assertStatus(403)
            ->assertJson(['code' => 'ACCOUNT_DEACTIVATED']);

        // Carteira with deactivated user
        $resDeactivatedCarteira = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/carteira');
        $resDeactivatedCarteira->assertStatus(403)
            ->assertJson(['code' => 'ACCOUNT_DEACTIVATED']);
    }
}
