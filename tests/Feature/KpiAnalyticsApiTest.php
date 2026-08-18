<?php

namespace Tests\Feature;

use Tests\TestCase;

class KpiAnalyticsApiTest extends TestCase
{
    public function test_kpi_dashboard_aggregates_statewide_metrics(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        $response = $this->getJson('/api/kpis/dashboard');
        $response->assertStatus(200);

        $this->assertEquals(108000, $response->json('meta_populacional_egressos_es'));
        $this->assertArrayHasKey('total_atendimentos', $response->json());
        $this->assertArrayHasKey('taxa_remoto_pct', $response->json());
        $this->assertArrayHasKey('taxa_empregabilidade_pct', $response->json());
    }

    public function test_kpi_regional_aggregates_4_macroregions(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        $response = $this->getJson('/api/kpis/regional');
        $response->assertStatus(200);

        $this->assertArrayHasKey('macrorregioes', $response->json());
        $this->assertArrayHasKey('Metropolitana', $response->json('macrorregioes'));
        $this->assertArrayHasKey('Norte', $response->json('macrorregioes'));
        $this->assertArrayHasKey('Sul', $response->json('macrorregioes'));
        $this->assertArrayHasKey('Central', $response->json('macrorregioes'));
    }

    public function test_kpi_telemetria_returns_mos_quality_distribution(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'gestor']);

        $response = $this->getJson('/api/kpis/telemetria');
        $response->assertStatus(200);

        $this->assertArrayHasKey('mos_distribuicao_percentual', $response->json());
        $this->assertArrayHasKey('qualidade_media_mos', $response->json());
    }
}
