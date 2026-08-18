<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;

class QrCodeSecurityServiceTest extends TestCase
{
    protected QrCodeSecurityService $qrService;
    protected LgpdSecurityService $lgpdService;
    protected string $signingKey = 'test_sejus_carteira_key_2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->lgpdService = new LgpdSecurityService('test_pepper');
        $this->qrService = new QrCodeSecurityService($this->lgpdService, $this->signingKey);
    }

    public function test_signs_payload_with_hmac_sha256(): void
    {
        $payload = [
            'doc_id' => '1',
            'registro_sejus' => 'ES-2026-000001',
            'nome' => 'LUCAS SANTOS',
            'municipio' => 'São Mateus',
        ];

        $signature = $this->qrService->signPayload($payload);
        $this->assertEquals(64, strlen($signature));
    }

    public function test_generates_and_verifies_genuine_token(): void
    {
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

        $token = $this->qrService->generateToken($payload);
        $this->assertNotEmpty($token);

        $verification = $this->qrService->verifyToken($token);
        $this->assertTrue($verification['valid']);
        $this->assertEquals('VALID_DOCUMENT', $verification['status']);
        $this->assertEquals('LUCAS SANTOS', $verification['payload']['nome']);
    }

    public function test_rejects_tampered_token_payload(): void
    {
        $payload = [
            'doc_id' => '1',
            'registro_sejus' => 'ES-2026-000001',
            'nome' => 'LUCAS SANTOS',
            'expires_at' => date('c', strtotime('+1 year')),
        ];

        $signature = $this->qrService->signPayload($payload);

        // Adversary alters name in payload without recalculating HMAC
        $tamperedPayload = $payload;
        $tamperedPayload['nome'] = 'ROBERTO ADULTERADO';

        $envelope = ['p' => $tamperedPayload, 's' => $signature];
        $tamperedToken = rtrim(strtr(base64_encode(json_encode($envelope)), '+/', '-_'), '=');

        $verification = $this->qrService->verifyToken($tamperedToken);
        $this->assertFalse($verification['valid']);
        $this->assertEquals('TAMPERED_DOCUMENT', $verification['status']);
    }

    public function test_rejects_expired_token(): void
    {
        $payload = [
            'doc_id' => '1',
            'registro_sejus' => 'ES-2026-000001',
            'nome' => 'LUCAS SANTOS',
            'issued_at' => date('c', strtotime('-2 years')),
            'expires_at' => date('c', strtotime('-1 year')), // Expired
        ];

        $token = $this->qrService->generateToken($payload);
        $verification = $this->qrService->verifyToken($token);

        $this->assertFalse($verification['valid']);
        $this->assertEquals('EXPIRED_DOCUMENT', $verification['status']);
    }

    public function test_generates_qr_code_svg_and_data_uri(): void
    {
        $svg = $this->qrService->generateQrCodeSvg('https://conectaegresso.es.gov.br/validar-carteira/test');
        $this->assertStringContainsString('<svg', $svg);

        $dataUri = $this->qrService->generateQrCodeDataUri('https://conectaegresso.es.gov.br/validar-carteira/test');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }
}
