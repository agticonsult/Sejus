<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Egresso;
use Illuminate\Support\Facades\Http;

class CarteiraPdfRouteTest extends TestCase
{
    /**
     * Test GET /carteira/pdf returns a valid application/pdf response.
     */
    public function test_carteira_pdf_route_returns_pdf_stream(): void
    {
        $response = $this->get('/carteira/pdf');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('carteira-digital-sejus.pdf', $response->headers->get('Content-Disposition', ''));
    }

    /**
     * Test GET /carteira/pdf works when authenticated as an Egresso user.
     */
    public function test_carteira_pdf_route_authenticated_egresso(): void
    {
        $egresso = Egresso::first();
        if ($egresso && $egresso->user) {
            $response = $this->actingAs($egresso->user)->get('/carteira/pdf');
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        } else {
            $response = $this->get('/carteira/pdf');
            $this->assertEquals(200, $response->getStatusCode());
        }
    }
}
