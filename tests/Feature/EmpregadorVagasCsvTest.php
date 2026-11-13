<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Conhecimento;
use App\Models\VagaEmprego;
use App\Models\MunicipioEs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class EmpregadorVagasCsvTest extends TestCase
{
    use RefreshDatabase;

    protected User $empregador;
    protected User $gestor;
    protected User $egresso;
    protected MunicipioEs $vitoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->empregador = User::where('email', 'rh@parceirosejus.com.br')->firstOrFail();
        $this->gestor = User::where('email', 'gestor@sejus.es.gov.br')->firstOrFail();
        $this->egresso = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $this->vitoria = MunicipioEs::where('codigo_ibge', 3205309)->firstOrFail();
    }

    /**
     * 1. Perfil Empregador existe no banco e User::isEmpregador() funciona corretamente;
     * login redireciona para /oportunidades/inserir-vaga e carrega Inertia com initialTab = inserir-vaga.
     */
    public function test_empregador_profile_login_redirects_to_inserir_vaga_and_renders_panel(): void
    {
        $perfil = Perfil::empregadores()->first();
        $this->assertNotNull($perfil);
        $this->assertEquals('empregador', $perfil->slug);
        $this->assertTrue($this->empregador->isEmpregador());

        $loginResponse = $this->post('/login', [
            'email' => 'rh@parceirosejus.com.br',
            'password' => 'secret123',
        ]);

        $loginResponse->assertRedirect('/oportunidades/inserir-vaga');

        $panelResponse = $this->actingAs($this->empregador)->get('/oportunidades/inserir-vaga');
        $panelResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Oportunidades')
                ->where('initialTab', 'inserir-vaga')
                ->has('catalogoConhecimentos')
            );
    }

    /**
     * 2. Download do modelo CSV retorna UTF-8 BOM, os 6 campos exigidos e é imediatamente importável sem erros.
     */
    public function test_csv_template_download_has_expected_columns_and_is_directly_importable(): void
    {
        $response = $this->actingAs($this->empregador)->get('/api/vagas/modelo-csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('modelo_importacao_vagas.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertTrue(str_starts_with($content, "\xEF\xBB\xBF"), 'O CSV deve iniciar com UTF-8 BOM.');

        $clean = substr($content, 3);
        $lines = preg_split('/\r\n|\r|\n/', trim($clean));
        $headers = str_getcsv($lines[0], ';');

        $this->assertEquals([
            'Nome vaga',
            'Descrição vaga',
            'Quantidade vaga',
            'Data início',
            'Data fim',
            'Conhecimento',
        ], $headers);

        // Também testa rota web /vagas/modelo-csv
        $this->actingAs($this->empregador)->get('/vagas/modelo-csv')->assertStatus(200);

        // Importa o próprio arquivo modelo baixado para comprovar que ele é 100% válido
        $initialCount = VagaEmprego::count();
        $file = UploadedFile::fake()->createWithContent('modelo_importacao_vagas.csv', $content);

        $importResponse = $this->actingAs($this->empregador)->post('/api/vagas/importar-csv', [
            'arquivo' => $file,
        ]);

        $importResponse->assertStatus(201)
            ->assertJsonPath('status', 'imported')
            ->assertJsonPath('sucesso_count', 2)
            ->assertJsonPath('erro_count', 0);

        $this->assertEquals($initialCount + 2, VagaEmprego::count());
    }

    /**
     * 3. Importa CSV válido com delimitadores ; e ,, "imediato", "permanente", datas DD/MM/YYYY e YYYY-MM-DD,
     * e múltiplos conhecimentos (incluindo habilidade com barra "/" como MIG/MAG).
     */
    public function test_csv_import_persists_vacancies_and_associates_system_conhecimentos(): void
    {
        $skillSoldagem = Conhecimento::where('nome', 'Soldagem Industrial (Eletrodo e MIG/MAG)')->firstOrFail();
        $skillEletrica = Conhecimento::where('nome', 'Instalações Elétricas Prediais (NR-10)')->firstOrFail();
        $skillLogistica = Conhecimento::create([
            'nome' => 'Logística, Almoxarifado e Estoque',
            'categoria' => 'logistica',
            'ativo' => true,
        ]);

        $csvRows = [
            'Nome vaga;Descrição vaga;Quantidade vaga;Data início;Data fim;Conhecimento',
            'Soldador Montador;Soldagem de estruturas metálicas pesadas;4;imediato;permanente;Soldagem Industrial (Eletrodo e MIG/MAG) | Instalações Elétricas Prediais (NR-10)',
            'Conferente de Carga;Conferência de notas e expedição;2;15/12/2026;2027-06-30;Logística, Almoxarifado e Estoque',
        ];
        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $csvRows);

        $file = UploadedFile::fake()->createWithContent('vagas_lote.csv', $csvContent);

        $response = $this->actingAs($this->empregador)->post('/api/vagas/importar-csv', [
            'arquivo' => $file,
            'municipio_id' => $this->vitoria->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('sucesso_count', 2)
            ->assertJsonPath('erro_count', 0);

        $vagaSoldador = VagaEmprego::where('titulo', 'Soldador Montador')->firstOrFail();
        $this->assertEquals($this->empregador->id, $vagaSoldador->user_id);
        $this->assertEquals(4, $vagaSoldador->vagas_totais);
        $this->assertTrue($vagaSoldador->inicio_imediato);
        $this->assertNull($vagaSoldador->data_inicio);
        $this->assertTrue($vagaSoldador->fim_permanente);
        $this->assertNull($vagaSoldador->data_fim);
        $this->assertEqualsCanonicalizing(
            [$skillSoldagem->id, $skillEletrica->id],
            $vagaSoldador->conhecimentos()->pluck('conhecimentos.id')->all()
        );

        $vagaConferente = VagaEmprego::where('titulo', 'Conferente de Carga')->firstOrFail();
        $this->assertFalse($vagaConferente->inicio_imediato);
        $this->assertEquals('2026-12-15', $vagaConferente->data_inicio?->format('Y-m-d'));
        $this->assertFalse($vagaConferente->fim_permanente);
        $this->assertEquals('2027-06-30', $vagaConferente->data_fim?->format('Y-m-d'));
        $this->assertEquals([$skillLogistica->id], $vagaConferente->conhecimentos()->pluck('conhecimentos.id')->all());

        // Testa também delimitador por vírgula (,)
        $commaCsv = implode("\n", [
            'Nome vaga,Descrição vaga,Quantidade vaga,Data início,Data fim,Conhecimento',
            '"Eletricista de Manutenção","Instalação de quadros trifásicos",1,imediato,permanente,"Instalações Elétricas Prediais (NR-10)"',
        ]);
        $commaFile = UploadedFile::fake()->createWithContent('vagas_virgula.csv', $commaCsv);

        $this->actingAs($this->empregador)
            ->post('/api/vagas/importar-csv', ['arquivo' => $commaFile])
            ->assertStatus(201)
            ->assertJsonPath('sucesso_count', 1);
    }

    /**
     * 4. Rejeita CSV com erros de validação (422), reportando linha/campo claros e fazendo rollback atômico.
     */
    public function test_csv_import_validates_fields_dates_and_catalog_skills_with_atomic_rollback(): void
    {
        $initialCount = VagaEmprego::count();

        // Linha 2 é válida, Linha 3 tem quantidade inválida, data inválida (31/02/2026) e conhecimento inexistente,
        // Linha 4 tem data_fim anterior a data_inicio
        $invalidCsv = implode("\n", [
            'Nome vaga;Descrição vaga;Quantidade vaga;Data início;Data fim;Conhecimento',
            'Vaga Válida Que Não Deve Persistir;Descrição ok;2;imediato;permanente;Instalações Elétricas Prediais (NR-10)',
            ';Descrição sem título;0;31/02/2026;permanente;Habilidade Inexistente XYZ',
            'Auxiliar Geral;Limpeza e apoio;2;20/10/2026;10/10/2026;Instalações Elétricas Prediais (NR-10)',
        ]);

        $file = UploadedFile::fake()->createWithContent('lote_invalido.csv', $invalidCsv);

        $response = $this->actingAs($this->empregador)->post('/api/vagas/importar-csv', [
            'arquivo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'validation_error')
            ->assertJsonPath('sucesso_count', 0);

        $erros = collect($response->json('erros'));
        $this->assertGreaterThanOrEqual(4, $erros->count());
        $this->assertTrue($erros->contains(fn ($e) => $e['linha'] === 3 && $e['campo'] === 'Nome vaga'));
        $this->assertTrue($erros->contains(fn ($e) => $e['linha'] === 3 && $e['campo'] === 'Quantidade vaga'));
        $this->assertTrue($erros->contains(fn ($e) => $e['linha'] === 3 && $e['campo'] === 'Data início'));
        $this->assertTrue($erros->contains(fn ($e) => $e['linha'] === 3 && $e['campo'] === 'Conhecimento'));
        $this->assertTrue($erros->contains(fn ($e) => $e['linha'] === 4 && $e['campo'] === 'Data fim'));

        // Garante atomicidade: nenhuma vaga da linha 2 foi gravada
        $this->assertEquals($initialCount, VagaEmprego::count());
        $this->assertDatabaseMissing('vagas_emprego', ['titulo' => 'Vaga Válida Que Não Deve Persistir']);
    }

    /**
     * 5. Rejeita arquivo vazio, cabeçalho incompleto e extensão não-CSV.
     */
    public function test_csv_import_rejects_empty_file_missing_headers_and_wrong_extension(): void
    {
        $emptyFile = UploadedFile::fake()->createWithContent('vazio.csv', '   ');
        $this->actingAs($this->empregador)
            ->post('/api/vagas/importar-csv', ['arquivo' => $emptyFile])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error');

        $badHeaderFile = UploadedFile::fake()->createWithContent('sem_colunas.csv', "Titulo;Descricao\nAuxiliar;Teste");
        $this->actingAs($this->empregador)
            ->post('/api/vagas/importar-csv', ['arquivo' => $badHeaderFile])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error');

        $exeFile = UploadedFile::fake()->createWithContent('malicioso.pdf', '%PDF-1.4');
        $this->actingAs($this->empregador)
            ->post('/api/vagas/importar-csv', ['arquivo' => $exeFile])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error');
    }

    /**
     * 6. Controle de acesso (RBAC) e propriedade (ownership) de vagas entre empregadores.
     */
    public function test_rbac_and_ownership_restrictions_on_vacancy_endpoints(): void
    {
        // Não autenticado -> 401 na API
        $this->getJson('/api/vagas/modelo-csv')->assertStatus(401);
        $this->postJson('/api/vagas/importar-csv', [])->assertStatus(401);
        $this->postJson('/api/vagas', [])->assertStatus(401);

        // Egresso autenticado -> 403 em modelo CSV, importação CSV, painel inserir-vaga e criação manual
        $this->actingAs($this->egresso)->get('/oportunidades/inserir-vaga')->assertStatus(403);
        $this->actingAs($this->egresso)->getJson('/api/vagas/modelo-csv')->assertStatus(403);
        $this->actingAs($this->egresso)->postJson('/api/vagas/importar-csv', [])->assertStatus(403);
        $this->actingAs($this->egresso)->postJson('/api/vagas', [
            'titulo' => 'Tentativa Egresso',
            'descricao' => 'Não deve criar',
            'vagas_totais' => 1,
        ])->assertStatus(403);

        // Empregador A cria vaga manualmente com conhecimentos do sistema
        $skill = Conhecimento::where('ativo', true)->firstOrFail();
        $createResp = $this->actingAs($this->empregador)->postJson('/api/vagas', [
            'nome_vaga' => 'Auxiliar de Almoxarifado Manual',
            'descricao' => 'Organização de materiais e inventário.',
            'quantidade_vaga' => 3,
            'inicio_imediato' => true,
            'fim_permanente' => true,
            'conhecimentos' => [$skill->id],
        ]);

        $createResp->assertStatus(201);
        $vagaId = $createResp->json('data.id');

        // Empregador B não pode editar nem excluir a vaga do Empregador A
        $outroEmpregador = User::create([
            'perfil_id' => $this->empregador->perfil_id,
            'municipio_id' => $this->vitoria->id,
            'name' => 'Outra Empresa Capixaba SA',
            'email' => 'contato@outraempresa.com.br',
            'password' => bcrypt('SecretPass2026!'),
            'cpf' => '11144477735',
            'ativo' => true,
        ]);

        $this->actingAs($outroEmpregador)
            ->putJson("/api/vagas/{$vagaId}", ['titulo' => 'Alteração Indevida'])
            ->assertStatus(403);

        $this->actingAs($outroEmpregador)
            ->deleteJson("/api/vagas/{$vagaId}")
            ->assertStatus(403);

        // O próprio Empregador A consegue atualizar sua vaga
        $this->actingAs($outroEmpregador)
            ->getJson('/api/vagas?q=Almoxarifado&municipio=Vitória')
            ->assertStatus(200);

        $this->actingAs($this->empregador)
            ->putJson("/api/vagas/{$vagaId}", ['vagas_totais' => 5])
            ->assertStatus(200)
            ->assertJsonPath('data.vagas_totais', 5);
    }

    /**
     * 7. Suporta CSV exportado pelo Excel em Windows-1252 (ISO-8859-1) e múltiplos conhecimentos
     * separados por "|" onde um dos conhecimentos contém vírgula no próprio nome.
     */
    public function test_csv_import_handles_windows1252_encoding_and_skills_with_inner_commas(): void
    {
        $skillSoldagem = Conhecimento::where('nome', 'Soldagem Industrial (Eletrodo e MIG/MAG)')->firstOrFail();
        $skillComVirgula = Conhecimento::create([
            'nome' => 'Logística, Almoxarifado e Estoque',
            'categoria' => 'logistica',
            'ativo' => true,
        ]);

        $utf8Csv = implode("\r\n", [
            'Nome vaga;Descrição vaga;Quantidade vaga;Data início;Data fim;Conhecimento',
            'Encarregado de Pátio;Coordenação de carga, descarga e manutenção;2;imediato;permanente;Logística, Almoxarifado e Estoque | Soldagem Industrial (Eletrodo e MIG/MAG)',
        ]);

        // Converte para Windows-1252 (sem BOM UTF-8), como o Excel em Português costuma exportar
        $win1252Csv = mb_convert_encoding($utf8Csv, 'Windows-1252', 'UTF-8');
        $file = UploadedFile::fake()->createWithContent('vagas_excel_ansi.csv', $win1252Csv);

        $tecnico = User::where('email', 'marcia.oliveira@sejus.es.gov.br')->firstOrFail();
        $this->actingAs($tecnico)->get('/vagas/modelo-csv')->assertStatus(200);

        $response = $this->actingAs($this->empregador)->post('/vagas/importar-csv', [
            'arquivo' => $file,
            'municipio_id' => $this->vitoria->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'imported')
            ->assertJsonPath('sucesso_count', 1);

        $vaga = VagaEmprego::where('titulo', 'Encarregado de Pátio')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$skillComVirgula->id, $skillSoldagem->id],
            $vaga->conhecimentos()->pluck('conhecimentos.id')->all()
        );
    }
}
