<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;
use App\Models\Egresso;
use App\Models\MunicipioEs;

class CarteiraValidationControllerTest extends TestCase
{
    public function test_public_validation_route_renders_valid_status_for_signed_token(): void
    {
        $lgpd = new LgpdSecurityService('test_pepper');
        $qrService = new QrCodeSecurityService($lgpd, 'sejus_carteira_digital_master_key_2026');

        $payload = [
            'doc_id' => '1',
            'registro_sejus' => 'ES-2026-000001',
            'cpf_masked' => '***.830.456-**',
            'nome' => 'LUCAS SANTOS',
            'municipio' => 'São Mateus',
            'issued_at' => date('c'),
            'expires_at' => date('c', strtotime('+1 year')),
            'legal_basis' => 'Lei Complementar Estadual nº 182/2021',
        ];

        $token = $qrService->generateToken($payload);

        $response = $this->get('/validar-carteira/' . $token);
        $response->assertStatus(200);
    }

    public function test_public_validation_api_endpoint_returns_json_response(): void
    {
        $lgpd = new LgpdSecurityService('test_pepper');
        $qrService = new QrCodeSecurityService($lgpd, 'sejus_carteira_digital_master_key_2026');

        $payload = [
            'doc_id' => '1',
            'registro_sejus' => 'ES-2026-000001',
            'cpf_masked' => '***.830.456-**',
            'nome' => 'LUCAS SANTOS',
            'expires_at' => date('c', strtotime('+1 year')),
        ];

        $token = $qrService->generateToken($payload);

        $response = $this->getJson('/api/validar-carteira/' . $token);
        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'status' => 'VALID_DOCUMENT',
            ]);
    }
}
