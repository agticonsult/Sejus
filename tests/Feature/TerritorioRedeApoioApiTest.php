<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MunicipioEs;
use App\Models\RedeApoio;

class TerritorioRedeApoioApiTest extends TestCase
{
    public function test_territorios_list_returns_78_es_municipalities(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        $response = $this->getJson('/api/territorios');
        $response->assertStatus(200);
        $this->assertEquals(78, $response->json('total_municipios_es'));
    }

    public function test_rejects_non_es_ibge_code(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        // Rio de Janeiro IBGE code (prefix 33)
        $response = $this->getJson('/api/territorios/3304557');
        $response->assertStatus(422);
    }

    public function test_rede_apoio_list_with_gps_fallback_policy(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $response = $this->getJson('/api/rede-apoio');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }
}
