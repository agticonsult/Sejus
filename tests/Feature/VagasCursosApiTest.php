<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\MunicipioEs;

class VagasCursosApiTest extends TestCase
{
    public function test_vagas_list_with_affirmative_action_filter(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $response = $this->getJson('/api/vagas?afirmativa_egresso=true');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public function test_vagas_list_with_negative_salary_clamping(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $response = $this->getJson('/api/vagas?salario_min=-500');
        $response->assertStatus(200);
    }

    public function test_cursos_list_with_ead_filter(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $response = $this->getJson('/api/cursos?ead_only=true');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }
}
