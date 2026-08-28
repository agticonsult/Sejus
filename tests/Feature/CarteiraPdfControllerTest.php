<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Egresso;
use App\Models\MunicipioEs;
use App\Services\LgpdSecurityService;
use Illuminate\Support\Facades\Http;

class CarteiraPdfControllerTest extends TestCase
{
    protected LgpdSecurityService $lgpdService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lgpdService = new LgpdSecurityService('test_pepper');
    }

    public function test_unauthenticated_user_can_download_pdf_with_fallback_egresso(): void
    {
        $response = $this->get('/carteira/pdf');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline; filename="carteira-digital-sejus.pdf"', $response->headers->get('Content-Disposition'));
        
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_download_pdf_consumes_document_generator_microservice(): void
    {
        $mockPdf = "%PDF-1.7\n%Document Generator Output API\nMock Binary PDF Data for SEJUS";

        Http::fake([
            '*/generate' => Http::response($mockPdf, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $response = $this->get('/carteira/pdf');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($mockPdf, $response->getContent());

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/generate')
                && $request->header('X-API-Key')[0] === config('services.document_generator.key', 'token-secreto-dev')
                && $request['format'] === 'A4';
        });
    }

    public function test_authenticated_egresso_can_download_own_pdf(): void
    {
        $perfil = Perfil::firstOrCreate(['slug' => 'egresso'], ['nome' => 'Egresso', 'descricao' => 'Egresso']);
        $municipio = MunicipioEs::firstOrCreate(
            ['codigo_ibge' => '3205309'],
            [
                'nome' => 'Vitória',
                'microrregiao' => 'Metropolitana',
                'macrorregiao' => 'Central',
                'latitude' => -20.3155,
                'longitude' => -40.3128,
                'tem_escritorio_fisico' => true,
                'populacao_estimada' => 365855,
            ]
        );

        $user = User::create([
            'perfil_id' => $perfil->id,
            'name' => 'Carlos Alberto Silva',
            'email' => 'carlos.egresso@test.es.gov.br',
            'password' => 'secret123',
            'cpf_encrypted' => $this->lgpdService->encryptField('12345678901'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('12345678901'),
            'ativo' => true,
        ]);

        $egresso = Egresso::create([
            'user_id' => $user->id,
            'nome_completo' => 'Carlos Alberto Silva',
            'cpf_encrypted' => $this->lgpdService->encryptField('12345678901'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('12345678901'),
            'municipio_residencia_id' => $municipio->id,
            'status_penal' => 'liberdade_definitiva',
        ]);

        $response = $this->actingAs($user)->get('/carteira/pdf');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_authenticated_gestor_can_download_pdf_for_specific_egresso(): void
    {
        $perfilGestor = Perfil::firstOrCreate(['slug' => 'gestor'], ['nome' => 'Gestor', 'descricao' => 'Gestor']);
        $municipio = MunicipioEs::firstOrCreate(
            ['codigo_ibge' => '3205309'],
            [
                'nome' => 'Vitória',
                'microrregiao' => 'Metropolitana',
                'macrorregiao' => 'Central',
                'latitude' => -20.3155,
                'longitude' => -40.3128,
                'tem_escritorio_fisico' => true,
                'populacao_estimada' => 365855,
            ]
        );

        $gestor = User::create([
            'perfil_id' => $perfilGestor->id,
            'name' => 'Dra. Gestora Silva',
            'email' => 'gestora.silva@sejus.es.gov.br',
            'password' => 'secret123',
            'ativo' => true,
        ]);

        $targetEgresso = Egresso::create([
            'nome_completo' => 'Roberto Medeiros Alencar',
            'cpf_encrypted' => $this->lgpdService->encryptField('98765432100'),
            'hash_cpf' => $this->lgpdService->generateBlindIndex('98765432100'),
            'municipio_residencia_id' => $municipio->id,
            'status_penal' => 'livramento_condicional',
        ]);

        $response = $this->actingAs($gestor)->get('/carteira/pdf?egresso_id=' . $targetEgresso->id);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
