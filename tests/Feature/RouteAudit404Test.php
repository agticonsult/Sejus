<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteAudit404Test extends TestCase
{
    /**
     * Test all core web routes return non-404 status codes.
     */
    public function test_all_web_routes_respond_without_404(): void
    {
        $routes = [
            '/dashboard',
            '/atendimento',
            '/oportunidades',
            '/carteira',
            '/geolocalizacao',
            '/prontuario',
            '/relatorios',
            '/seguranca-lgpd',
            '/validar-carteira',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $this->assertNotEquals(
                404,
                $response->getStatusCode(),
                "Route '{$route}' returned 404 Not Found"
            );
        }
    }

    /**
     * Test public carteira validation route with token.
     */
    public function test_public_carteira_validation_with_token(): void
    {
        $response = $this->get('/validar-carteira/test_token_123');
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
