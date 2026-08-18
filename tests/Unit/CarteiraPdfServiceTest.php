<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\CarteiraPdfService;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;
use App\Models\Egresso;
use App\Models\MunicipioEs;

class CarteiraPdfServiceTest extends TestCase
{
    protected CarteiraPdfService $pdfService;
    protected QrCodeSecurityService $qrService;
    protected LgpdSecurityService $lgpdService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lgpdService = new LgpdSecurityService('test_pepper');
        $this->qrService = new QrCodeSecurityService($this->lgpdService, 'test_key');
        $this->pdfService = new CarteiraPdfService($this->qrService, $this->lgpdService);
    }

    public function test_renders_html_with_official_sejus_elements(): void
    {
        $egresso = new Egresso([
            'id' => 1,
            'nome_completo' => 'Lucas Santos',
            'cpf_encrypted' => $this->lgpdService->encryptField('19283045678'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('19283045678'),
        ]);
        $egresso->id = 1;

        $html = $this->pdfService->renderHtml($egresso);

        $this->assertStringContainsString('GOVERNO DO ESTADO DO ESPÍRITO SANTO', $html);
        $this->assertStringContainsString('SECRETARIA DE ESTADO DA JUSTIÇA', $html);
        $this->assertStringContainsString('LUCAS SANTOS', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('182/2021', $html);
    }
}
