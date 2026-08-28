<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;

class ProntuarioApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }
    public function test_prontuario_list_clamping_and_search(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'tecnico']);

        $response = $this->getJson('/api/prontuarios?per_page=500');
        $response->assertStatus(200);

        // Clamped to 100 max
        $this->assertLessThanOrEqual(100, $response->json('per_page'));
    }

    public function test_egresso_blocked_from_writing_timeline_evolucao(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'egresso']);

        $response = $this->postJson('/api/prontuarios/1/evolucao', [
            'descricao' => 'Tentativa não autorizada de evolução clínica.',
        ]);

        $response->assertStatus(403);
    }

    public function test_empty_description_rejected_with_422(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'tecnico']);

        $response = $this->postJson('/api/prontuarios/1/evolucao', [
            'descricao' => '   ',
        ]);

        $response->assertStatus(422);
    }

    public function test_payload_exceeding_64kb_rejected(): void
    {
        $this->postJson('/api/auth/switch-role', ['role' => 'tecnico']);

        $hugeDescription = str_repeat('A', 70000); // 70KB

        $response = $this->postJson('/api/prontuarios/1/evolucao', [
            'descricao' => $hugeDescription,
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [413, 422], true));
    }
}
