<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Egresso;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\Prontuario;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;

class MobileCoreEndpointsTest extends TestCase
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

        return $token;
    }

    /**
     * R2: Authenticated Egresso GET /api/carteira returning wallet record,
     * registro_sejus, token, qr_code_data_uri, and validation_url.
     */
    public function test_egresso_can_fetch_own_digital_wallet_carteira(): void
    {
        $token = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/carteira');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'registro_sejus',
                    'nome',
                    'cpf_masked',
                    'status_penal',
                    'status_documento',
                    'token',
                    'qr_code_data_uri',
                    'auth_code',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.registro_sejus'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.qr_code_data_uri'));
        $this->assertStringStartsWith('data:image/', $response->json('data.qr_code_data_uri'));
        $this->assertStringContainsString('***', $response->json('data.cpf_masked'));
    }

    /**
     * R2: Unauthenticated GET /api/carteira returns 401.
     */
    public function test_unauthenticated_request_to_carteira_returns_401(): void
    {
        $response = $this->getJson('/api/carteira');

        $response->assertStatus(401);
    }

    /**
     * R2: User without an Egresso profile GET /api/carteira returns 404.
     */
    public function test_carteira_returns_404_for_user_without_egresso_profile(): void
    {
        $gestorToken = $this->getMobileToken('gestor@sejus.es.gov.br', 'secret123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $gestorToken)
            ->getJson('/api/carteira');

        $response->assertStatus(404);
    }

    /**
     * R2: Public GET /api/validar-carteira/{token} returning 200 with valid: true.
     */
    public function test_digital_wallet_public_verification_endpoint_valid(): void
    {
        $token = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123');

        $carteiraRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/carteira');
        $carteiraRes->assertStatus(200);

        $walletToken = $carteiraRes->json('data.token');
        $this->assertNotEmpty($walletToken);

        $response = $this->getJson('/api/validar-carteira/' . $walletToken);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'status' => 'VALID_DOCUMENT',
            ]);

        $this->assertNotNull($response->json('payload'));
        $this->assertNotEmpty($response->json('payload.registro_sejus'));
    }

    /**
     * R2: Public GET /api/validar-carteira/{token} with tampered token returning valid: false.
     */
    public function test_digital_wallet_public_verification_endpoint_tampered(): void
    {
        $token = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123');

        $carteiraRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/carteira');
        $walletToken = $carteiraRes->json('data.token');

        $tamperedToken = substr($walletToken, 0, -6) . 'TAMPER';

        $response = $this->getJson('/api/validar-carteira/' . $tamperedToken);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
            ]);
    }

    /**
     * R2: Authenticated GET /api/vagas returning job vacancies with updated_at.
     */
    public function test_vagas_list_accessible_via_mobile_token(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vagas');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'Vagas list should not be empty after seeding.');

        $firstVaga = $data[0];
        $this->assertArrayHasKey('id', $firstVaga);
        $this->assertArrayHasKey('titulo', $firstVaga);
        $this->assertArrayHasKey('empresa', $firstVaga);
        $this->assertArrayHasKey('status', $firstVaga);
        $this->assertArrayHasKey('updated_at', $firstVaga);
        $this->assertNotEmpty($firstVaga['updated_at']);
    }

    /**
     * R2: Authenticated GET /api/cursos returning vocational courses with updated_at.
     */
    public function test_cursos_list_accessible_via_mobile_token(): void
    {
        $token = $this->getMobileToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/cursos');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'Cursos list should not be empty after seeding.');

        $firstCurso = $data[0];
        $this->assertArrayHasKey('id', $firstCurso);
        $this->assertArrayHasKey('titulo', $firstCurso);
        $this->assertArrayHasKey('instituicao', $firstCurso);
        $this->assertArrayHasKey('status', $firstCurso);
        $this->assertArrayHasKey('updated_at', $firstCurso);
        $this->assertNotEmpty($firstCurso['updated_at']);
    }

    /**
     * R2: Authenticated Egresso GET /api/prontuarios returning strictly egresso's own record.
     */
    public function test_prontuarios_for_egresso_returns_strictly_own_record(): void
    {
        // Lucas Santos (egresso_id = 1, user_id = 3)
        $token = $this->getMobileToken('lucas.santos@cidadao.es.gov.br', 'secret123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/prontuarios');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Must strictly return egresso's own record (count 1)
        $this->assertCount(1, $data, 'Egresso must strictly receive their own prontuário.');

        $record = $data[0];
        $this->assertEquals('PRT-2026-000001', $record['numero_prontuario']);

        // Check egresso relation inside prontuário
        $this->assertEquals(1, $record['egresso_id'] ?? $record['egresso']['id']);

        // Assert Roberto Fonseca's prontuário (PRT-2026-000002) is NOT present
        $numbers = array_column($data, 'numero_prontuario');
        $this->assertNotContains('PRT-2026-000002', $numbers);
    }

    /**
     * R2: Unauthenticated GET /api/prontuarios returns 401.
     */
    public function test_unauthenticated_request_to_prontuarios_returns_401(): void
    {
        $response = $this->getJson('/api/prontuarios');

        $response->assertStatus(401);
    }
}
