<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Perfil;

class AuthControllerTest extends TestCase
{
    public function test_auth_me_returns_unauthenticated_when_not_logged_in(): void
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    public function test_switch_role_endpoint_simulates_gestor_login(): void
    {
        $response = $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'role_switched',
                'user' => [
                    'role' => 'gestor',
                ],
            ]);
    }

    public function test_switch_role_endpoint_simulates_tecnico_login(): void
    {
        $response = $this->postJson('/api/auth/switch-role', ['role' => 'tecnico']);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'role_switched',
                'user' => [
                    'role' => 'tecnico',
                ],
            ]);
    }

    public function test_switch_role_endpoint_simulates_egresso_login(): void
    {
        $response = $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'role_switched',
                'user' => [
                    'role' => 'egresso',
                ],
            ]);
    }

    public function test_govbr_oidc_login_simulation(): void
    {
        $payload = [
            'sub' => 'govbr_unique_998877',
            'cpf' => '529.982.247-25',
            'name' => 'Dr. Gestor SEJUS Teste',
            'email' => 'gestor.teste@sejus.es.gov.br',
            'nivel_confianca' => 'Ouro',
            'orgao' => 'SEJUS',
            'cargo' => 'Gestor de Reintegração Social',
        ];

        $response = $this->postJson('/api/auth/govbr/login', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authenticated',
                'user' => [
                    'role' => 'gestor',
                ],
            ]);
    }

    public function test_logout_invalidates_session(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'tecnico']);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(200)
            ->assertJson(['status' => 'logged_out']);
    }
}
