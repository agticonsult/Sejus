<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CarteiraPdfService;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;
use App\Models\Egresso;
use App\Models\MunicipioEs;
use Illuminate\Support\Facades\Http;

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

    public function test_generate_pdf_uses_document_generator_api_when_successful(): void
    {
        $mockPdf = "%PDF-1.7\n%Document Generator Output\nMock Binary PDF Content";

        Http::fake([
            '*/generate' => Http::response($mockPdf, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $egresso = new Egresso([
            'nome_completo' => 'Lucas Santos',
            'cpf_encrypted' => $this->lgpdService->encryptField('19283045678'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('19283045678'),
        ]);
        $egresso->id = 1;

        $pdf = $this->pdfService->generatePdf($egresso);

        $this->assertSame($mockPdf, $pdf);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/generate')
                && $request->header('X-API-Key')[0] === config('services.document_generator.key', 'token-secreto-dev')
                && isset($request['html'])
                && $request['format'] === 'A4';
        });
    }

    public function test_generate_pdf_falls_back_to_dompdf_on_api_failure(): void
    {
        Http::fake([
            '*/generate' => Http::response('Internal Server Error', 500),
        ]);

        $egresso = new Egresso([
            'nome_completo' => 'Lucas Santos',
            'cpf_encrypted' => $this->lgpdService->encryptField('19283045678'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('19283045678'),
        ]);
        $egresso->id = 1;

        $pdf = $this->pdfService->generatePdf($egresso);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_generate_pdf_falls_back_to_dompdf_on_api_timeout(): void
    {
        Http::fake([
            '*/generate' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out after 5000ms');
            },
        ]);

        $egresso = new Egresso([
            'nome_completo' => 'Lucas Santos',
            'cpf_encrypted' => $this->lgpdService->encryptField('19283045678'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('19283045678'),
        ]);
        $egresso->id = 1;

        $pdf = $this->pdfService->generatePdf($egresso);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
