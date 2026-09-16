<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Egresso;
use Illuminate\Support\Facades\Hash;

class MobileAuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * R1: Valid login with email returning Bearer token and user profile.
     */
    public function test_mobile_login_with_valid_email_returns_bearer_token(): void
    {
        $payload = [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'secret123',
            'device_name' => 'iPhone 15 Pro (iOS 18.0)',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authenticated',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'lucas.santos@cidadao.es.gov.br',
                    'role' => 'egresso',
                ],
            ]);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertIsString($response->json('access_token'));
        $this->assertNotNull($response->json('user.id'));
        $this->assertNotNull($response->json('user.cpf_masked'));
        $this->assertStringContainsString('***', $response->json('user.cpf_masked'));
    }

    /**
     * R1: Valid login with formatted CPF returning Bearer token.
     */
    public function test_mobile_login_with_formatted_cpf_returns_bearer_token(): void
    {
        $payload = [
            'login' => '192.830.456-78',
            'password' => 'secret123',
            'device_name' => 'Pixel 9 (Android 15)',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authenticated',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'lucas.santos@cidadao.es.gov.br',
                    'role' => 'egresso',
                ],
            ]);

        $this->assertNotEmpty($response->json('access_token'));
    }

    /**
     * R1: Valid login with unformatted CPF returning Bearer token.
     */
    public function test_mobile_login_with_unformatted_cpf_returns_bearer_token(): void
    {
        $payload = [
            'login' => '19283045678',
            'password' => 'secret123',
            'device_name' => 'Galaxy S24 (Android 14)',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'authenticated',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'lucas.santos@cidadao.es.gov.br',
                    'role' => 'egresso',
                ],
            ]);

        $this->assertNotEmpty($response->json('access_token'));
    }

    /**
     * R1: Invalid credentials returning 401 with code INVALID_CREDENTIALS.
     */
    public function test_mobile_login_with_invalid_credentials_returns_401(): void
    {
        $payload = [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'senha_errada_123',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'INVALID_CREDENTIALS',
            ]);

        $this->assertNotEmpty($response->json('error'));
    }

    /**
     * R1: Non-existent user login returning 401 with code INVALID_CREDENTIALS.
     */
    public function test_mobile_login_with_nonexistent_user_returns_401(): void
    {
        $payload = [
            'login' => 'naoexiste@sejus.es.gov.br',
            'password' => 'secret123',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    /**
     * R1: Deactivated account returning 403 with code ACCOUNT_DEACTIVATED.
     */
    public function test_mobile_login_with_deactivated_account_returns_403(): void
    {
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $user->ativo = false;
        $user->save();

        $payload = [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'secret123',
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_DEACTIVATED',
            ]);

        $this->assertNotEmpty($response->json('error'));
    }

    /**
     * R1: GET /api/auth/me with Bearer token returning user profile, permissions, masked CPF.
     */
    public function test_mobile_auth_me_with_valid_bearer_token_returns_profile(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'secret123',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('access_token');
        $this->assertNotEmpty($token);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'lucas.santos@cidadao.es.gov.br',
                'role' => 'egresso',
            ]);

        $this->assertNotEmpty($response->json('name'));
        $this->assertIsArray($response->json('permissions'));
        $this->assertNotNull($response->json('cpf_masked'));
        $this->assertStringContainsString('***', $response->json('cpf_masked'));
        $this->assertNotNull($response->json('egresso'));
        $this->assertEquals('Lucas Santos', $response->json('egresso.nome_completo'));
    }

    /**
     * R1: GET /api/auth/me without token returning 401.
     */
    public function test_mobile_auth_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * R1: GET /api/auth/me with invalid or tampered token returning 401.
     */
    public function test_mobile_auth_me_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_tampered_token_hash_value_9999')
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * R1: POST /api/auth/logout revoking token, subsequent request returning 401.
     */
    public function test_mobile_logout_revokes_token(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'login' => 'lucas.santos@cidadao.es.gov.br',
            'password' => 'secret123',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('access_token');
        $this->assertNotEmpty($token);

        // Verify token is active
        $meBefore = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
        $meBefore->assertStatus(200);

        // Perform logout
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJson([
                'status' => 'logged_out',
            ]);

        // Subsequent request using same token must fail with 401
        $meAfter = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $meAfter->assertStatus(401);
    }
}
