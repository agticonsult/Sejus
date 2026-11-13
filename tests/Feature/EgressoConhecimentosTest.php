<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Egresso;
use App\Models\Conhecimento;
use App\Models\EgressoConhecimento;
use App\Models\Prontuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EgressoConhecimentosTest extends TestCase
{
    use RefreshDatabase;

    protected User $gestor;
    protected User $tecnico;
    protected User $egressoUser;
    protected User $outroEgressoUser;
    protected Egresso $egresso;
    protected Egresso $outroEgresso;
    protected Prontuario $prontuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->gestor = User::where('email', 'gestor@sejus.es.gov.br')->firstOrFail();
        $this->tecnico = User::where('email', 'marcia.oliveira@sejus.es.gov.br')->firstOrFail();
        $this->egressoUser = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $this->outroEgressoUser = User::where('email', 'roberto.fonseca@cidadao.es.gov.br')->firstOrFail();

        $this->egresso = Egresso::where('user_id', $this->egressoUser->id)->firstOrFail();
        $this->outroEgresso = Egresso::where('user_id', $this->outroEgressoUser->id)->firstOrFail();
        $this->prontuario = Prontuario::where('egresso_id', $this->egresso->id)->firstOrFail();

        $this->egresso->conhecimentos()->detach();
    }

    /**
     * 1. GET /api/conhecimentos retorna o catálogo com status 200 e estrutura correta.
     */
    public function test_catalog_returns_available_skills_list(): void
    {
        Conhecimento::firstOrCreate(['nome' => 'Soldagem Industrial MIG/MAG'], ['categoria' => 'Indústria', 'ativo' => true]);
        Conhecimento::firstOrCreate(['nome' => 'Eletricista Predial'], ['categoria' => 'Construção Civil', 'ativo' => true]);

        $response = $this->actingAs($this->tecnico)->getJson('/api/conhecimentos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'nome', 'descricao', 'categoria', 'ativo']
                ],
                'total'
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    /**
     * 2. GET /api/conhecimentos?q=... filtra corretamente pelo termo de busca.
     */
    public function test_catalog_filters_by_search_term(): void
    {
        Conhecimento::firstOrCreate(['nome' => 'Pintura Residencial e Textura'], ['categoria' => 'Construção Civil', 'ativo' => true]);
        Conhecimento::firstOrCreate(['nome' => 'Carpintaria Náutica'], ['categoria' => 'Marcenaria', 'ativo' => true]);

        $response = $this->actingAs($this->egressoUser)->getJson('/api/conhecimentos?q=Pintura');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('nome');
        $this->assertTrue($names->contains('Pintura Residencial e Textura'));
        $this->assertFalse($names->contains('Carpintaria Náutica'));
    }

    /**
     * 3. Permite adicionar múltiplos conhecimentos sem restrição de quantidade.
     */
    public function test_can_add_multiple_skills_without_quantity_restriction(): void
    {
        $skillsPayload = [];
        for ($i = 1; $i <= 12; $i++) {
            $skillsPayload[] = [
                'nome' => "Habilidade Profissional {$i}",
                'nivel' => ($i % 3 === 0) ? 'avancado' : (($i % 2 === 0) ? 'intermediario' : 'basico'),
                'necessita_treinamento' => ($i % 2 === 0),
            ];
        }

        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            ['conhecimentos' => $skillsPayload]
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('total', 12);

        $this->assertEquals(12, $this->egresso->conhecimentos()->count());
        $this->assertEquals(12, EgressoConhecimento::where('egresso_id', $this->egresso->id)->count());
    }

    /**
     * 4. Suporta cadastro dinâmico de novos conhecimentos via firstOrCreate.
     */
    public function test_sync_dynamically_creates_novel_skills_via_first_or_create(): void
    {
        $novelSkillName = 'Operação de Drone Agrícola e Pulverização';
        $this->assertDatabaseMissing('conhecimentos', ['nome' => $novelSkillName]);

        $response = $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    [
                        'nome' => $novelSkillName,
                        'nivel' => 'intermediario',
                        'necessita_treinamento' => false,
                    ]
                ]
            ]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('conhecimentos', [
            'nome' => $novelSkillName,
            'ativo' => true,
        ]);

        $conhecimento = Conhecimento::where('nome', $novelSkillName)->firstOrFail();
        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $conhecimento->id,
            'nivel' => 'intermediario',
            'necessita_treinamento' => false,
        ]);
    }

    /**
     * 5. Edita o nível de proficiência (básico -> avançado) mantendo unicidade.
     */
    public function test_can_edit_proficiency_level(): void
    {
        $c = Conhecimento::firstOrCreate(['nome' => 'Mecânica Automotiva'], ['ativo' => true]);

        $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c->id, 'nivel' => 'basico', 'necessita_treinamento' => true]
                ]
            ]
        )->assertStatus(200);

        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $c->id,
            'nivel' => 'basico',
        ]);

        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c->id, 'nivel' => 'avancado', 'necessita_treinamento' => true]
                ]
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $c->id,
            'nivel' => 'avancado',
        ]);
        $this->assertEquals(1, $this->egresso->conhecimentos()->count());
    }

    /**
     * 6. Alterna o indicador booleano necessita_treinamento (false -> true).
     */
    public function test_can_toggle_necessita_treinamento_flag(): void
    {
        $c = Conhecimento::firstOrCreate(['nome' => 'Instalação de Painéis Solares'], ['ativo' => true]);

        $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c->id, 'nivel' => 'intermediario', 'necessita_treinamento' => false]
                ]
            ]
        )->assertStatus(200);

        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $c->id,
            'necessita_treinamento' => false,
        ]);

        $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c->id, 'nivel' => 'intermediario', 'necessita_treinamento' => true]
                ]
            ]
        )->assertStatus(200);

        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $c->id,
            'necessita_treinamento' => true,
        ]);
    }

    /**
     * 7. Remove conhecimento da ficha do egresso preservando o catálogo mestre.
     */
    public function test_can_remove_skill_while_master_catalog_remains_intact(): void
    {
        $c1 = Conhecimento::firstOrCreate(['nome' => 'Soldagem TIG'], ['ativo' => true]);
        $c2 = Conhecimento::firstOrCreate(['nome' => 'Torneiro Mecânico'], ['ativo' => true]);

        $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c1->id, 'nivel' => 'basico', 'necessita_treinamento' => false],
                    ['conhecimento_id' => $c2->id, 'nivel' => 'avancado', 'necessita_treinamento' => false],
                ]
            ]
        )->assertStatus(200);

        $this->assertEquals(2, $this->egresso->conhecimentos()->count());

        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c1->id, 'nivel' => 'basico', 'necessita_treinamento' => false],
                ]
            ]
        );

        $response->assertStatus(200)->assertJsonPath('total', 1);

        $this->assertDatabaseHas('egresso_conhecimentos', ['conhecimento_id' => $c1->id]);
        $this->assertDatabaseMissing('egresso_conhecimentos', ['conhecimento_id' => $c2->id]);
        $this->assertDatabaseHas('conhecimentos', ['id' => $c2->id, 'nome' => 'Torneiro Mecânico']);
        $this->assertEquals(1, $this->egresso->conhecimentos()->count());
    }

    /**
     * 8. Limpa todos os conhecimentos ao enviar array vazio.
     */
    public function test_can_clear_all_skills_with_empty_array(): void
    {
        $c = Conhecimento::firstOrCreate(['nome' => 'Almoxarifado'], ['ativo' => true]);
        $this->egresso->conhecimentos()->attach($c->id, ['nivel' => 'basico', 'necessita_treinamento' => false]);
        $this->assertEquals(1, $this->egresso->conhecimentos()->count());

        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            ['conhecimentos' => []]
        );

        $response->assertStatus(200)->assertJsonPath('total', 0);
        $this->assertEquals(0, $this->egresso->conhecimentos()->count());
    }

    /**
     * 9. ProntuarioController faz eager loading e retorna conhecimentos persistidos.
     */
    public function test_prontuario_eager_loads_and_returns_conhecimentos(): void
    {
        $c1 = Conhecimento::firstOrCreate(['nome' => 'Instalações Hidráulicas'], ['ativo' => true]);
        $this->egresso->conhecimentos()->attach($c1->id, [
            'nivel' => 'intermediario',
            'necessita_treinamento' => true,
        ]);

        $response = $this->actingAs($this->tecnico)->getJson("/api/prontuarios/{$this->prontuario->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'numero_prontuario',
                'egresso' => [
                    'id',
                    'nome_completo',
                    'conhecimentos' => [
                        '*' => ['conhecimento_id', 'nome', 'nivel', 'necessita_treinamento']
                    ]
                ]
            ]
        ]);

        $conhecimentos = $response->json('data.egresso.conhecimentos');
        $this->assertCount(1, $conhecimentos);
        $this->assertEquals('Instalações Hidráulicas', $conhecimentos[0]['nome']);
        $this->assertEquals('intermediario', $conhecimentos[0]['nivel']);
        $this->assertTrue($conhecimentos[0]['necessita_treinamento']);
    }

    /**
     * 10. Validação rejeita níveis inválidos com status 422.
     */
    public function test_validation_rejects_invalid_proficiency_levels(): void
    {
        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    [
                        'nome' => 'Carpintaria',
                        'nivel' => 'expert_invalido',
                        'necessita_treinamento' => false,
                    ]
                ]
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conhecimentos.0.nivel']);

        $this->assertDatabaseMissing('conhecimentos', ['nome' => 'Carpintaria']);
    }

    /**
     * 11. Validação rejeita item sem nome e sem ID com status 422.
     */
    public function test_validation_rejects_empty_item_without_id_or_name(): void
    {
        $response = $this->actingAs($this->tecnico)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    [
                        'conhecimento_id' => null,
                        'nome' => '',
                        'nivel' => 'basico',
                        'necessita_treinamento' => false,
                    ]
                ]
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * 12. Egresso é impedido de modificar a ficha de outro egresso (403 Forbidden).
     */
    public function test_egresso_cannot_modify_another_egressos_skills(): void
    {
        $response = $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->outroEgresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['nome' => 'Tentativa Não Autorizada', 'nivel' => 'basico', 'necessita_treinamento' => false]
                ]
            ]
        );

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertDatabaseMissing('conhecimentos', ['nome' => 'Tentativa Não Autorizada']);
    }

    /**
     * 13. Requisição não autenticada é rejeitada com status 401.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            ['conhecimentos' => []]
        );

        $response->assertStatus(401);
    }

    /**
     * 14. Suporta método POST além de PUT para sincronização.
     */
    public function test_post_method_is_also_accepted_for_sync(): void
    {
        $c = Conhecimento::firstOrCreate(['nome' => 'Logística Reversa'], ['ativo' => true]);

        $response = $this->actingAs($this->tecnico)->postJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c->id, 'nivel' => 'avancado', 'necessita_treinamento' => false]
                ]
            ]
        );

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('egresso_conhecimentos', [
            'egresso_id' => $this->egresso->id,
            'conhecimento_id' => $c->id,
            'nivel' => 'avancado',
        ]);
    }

    /**
     * 15. Página Inertia GET /prontuario recarrega os conhecimentos persistidos e preserva lista vazia [].
     */
    public function test_inertia_prontuario_view_reloads_persisted_skills_and_preserves_empty_array(): void
    {
        $c1 = Conhecimento::firstOrCreate(['nome' => 'Panificação e Confeitaria Artesanal'], ['ativo' => true]);
        $c2 = Conhecimento::firstOrCreate(['nome' => 'Atendimento ao Público e Vendas'], ['ativo' => true]);

        $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            [
                'conhecimentos' => [
                    ['conhecimento_id' => $c1->id, 'nivel' => 'avancado', 'necessita_treinamento' => false],
                    ['conhecimento_id' => $c2->id, 'nivel' => 'basico', 'necessita_treinamento' => true],
                ]
            ]
        )->assertStatus(200);

        $viewResponse = $this->actingAs($this->egressoUser)->get('/prontuario');
        $viewResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Prontuario')
                ->has('conhecimentos', 2)
                ->where('conhecimentos.0.nivel', 'avancado')
                ->where('conhecimentos.0.necessita_treinamento', false)
                ->where('conhecimentos.1.nivel', 'basico')
                ->where('conhecimentos.1.necessita_treinamento', true)
                ->has('catalogoConhecimentos')
            );

        // Limpa todos os conhecimentos e verifica se o reload não injeta mocks falsos
        $this->actingAs($this->egressoUser)->putJson(
            "/api/egressos/{$this->egresso->id}/conhecimentos",
            ['conhecimentos' => []]
        )->assertStatus(200);

        $emptyReload = $this->actingAs($this->egressoUser)->get('/prontuario');
        $emptyReload->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Prontuario')
                ->has('conhecimentos', 0)
            );
    }

    /**
     * 16. Perfis Empregador e Familiar são bloqueados (403) ao acessar prontuário ou API de conhecimentos do egresso.
     */
    public function test_empregador_and_familiar_are_forbidden_from_accessing_egresso_skills_and_prontuario(): void
    {
        $empregador = User::where('email', 'rh@parceirosejus.com.br')->firstOrFail();

        $familiarPerfil = \App\Models\Perfil::where('slug', 'familiar')->firstOrFail();
        $familiar = User::create([
            'perfil_id' => $familiarPerfil->id,
            'name' => 'Familiar Teste',
            'email' => 'familiar.teste@cidadao.es.gov.br',
            'password' => bcrypt('SecretPass2026!'),
            'cpf' => '12345678909',
            'ativo' => true,
        ]);

        foreach ([$empregador, $familiar] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get('/prontuario')
                ->assertStatus(403);

            $this->actingAs($unauthorizedUser)
                ->getJson("/api/egressos/{$this->egresso->id}/conhecimentos")
                ->assertStatus(403);

            $this->actingAs($unauthorizedUser)
                ->putJson("/api/egressos/{$this->egresso->id}/conhecimentos", [
                    'conhecimentos' => [
                        ['nome' => 'Acesso Indevido', 'nivel' => 'basico', 'necessita_treinamento' => false],
                    ],
                ])
                ->assertStatus(403);
        }
    }

    /**
     * 17. Busca textual de prontuários (?q=...) funciona em SQLite e o registro de evolução com parecer_tecnico retorna 201.
     */
    public function test_prontuario_sqlite_search_and_timeline_entry_creation(): void
    {
        $searchResp = $this->actingAs($this->tecnico)->getJson('/api/prontuarios?q=Lucas');
        $searchResp->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($searchResp->json('data')));

        $timelineResp = $this->actingAs($this->tecnico)->postJson(
            "/api/prontuarios/{$this->prontuario->id}/timeline",
            [
                'tipo_evento' => 'parecer_tecnico',
                'titulo' => 'Avaliação de Qualificação Profissional',
                'descricao' => 'Conhecimentos atualizados e encaminhamento para capacitação técnica.',
            ]
        );

        $timelineResp->assertStatus(201)
            ->assertJsonPath('data.tipo_evento', 'parecer_tecnico');
    }
}

