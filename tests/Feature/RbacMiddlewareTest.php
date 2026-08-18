<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Perfil;
use App\Http\Middleware\CheckRole;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RbacMiddlewareTest extends TestCase
{
    public function test_check_role_middleware_blocks_unauthenticated_requests(): void
    {
        $middleware = new CheckRole();
        $request = Request::create('/api/prontuarios', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }, 'gestor', 'tecnico');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_check_role_middleware_allows_authorized_role(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        $middleware = new CheckRole();
        $request = Request::create('/api/prontuarios', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }, 'gestor', 'tecnico');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_check_role_middleware_blocks_unauthorized_role(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $middleware = new CheckRole();
        $request = Request::create('/api/admin/relatorios', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }, 'gestor');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
