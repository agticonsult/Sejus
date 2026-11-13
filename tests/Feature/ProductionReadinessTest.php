<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\LgpdSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * 1. Verifica que todas as páginas Vue e layouts de interface estão livres de atalhos de testador,
     * credenciais expostas, auto-preenchimento (quickFill), seletor de troca de perfil e modais falsos Gov.br.
     */
    public function test_frontend_templates_contain_zero_tester_shortcuts_or_mock_credentials(): void
    {
        $loginVue = file_get_contents(base_path('resources/js/Pages/Login.vue'));
        $appLayoutVue = file_get_contents(base_path('resources/js/Layouts/AppLayout.vue'));
        $prontuarioVue = file_get_contents(base_path('resources/js/Pages/Prontuario.vue'));
        $oportunidadesVue = file_get_contents(base_path('resources/js/Pages/Oportunidades.vue'));

        $this->assertNotFalse($loginVue);
        $this->assertNotFalse($appLayoutVue);
        $this->assertNotFalse($prontuarioVue);
        $this->assertNotFalse($oportunidadesVue);

        // Login.vue: sem botões demo, auto-fill, senha hardcoded ou modal de persona Gov.br
        $forbiddenInLogin = [
            'demoAccounts',
            'quickFill',
            'secret123',
            'demoSuporteBtn',
            'demoGestorBtn',
            'demoTecnicoBtn',
            'demoEgressoBtn',
            'govBrPersonas',
            'showGovBrModal',
            'executeGovBrLogin',
        ];
        foreach ($forbiddenInLogin as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $loginVue,
                "Facilidade de testador '{$forbidden}' ainda presente em Login.vue"
            );
        }

        // AppLayout.vue: sem dropdown interativo de troca de perfil nem chamada a switch-role
        $forbiddenInLayout = [
            'userRoleSelect',
            'handleRoleChange',
            '/auth/switch-role',
            'Carlos Eduardo Silva',
            'lucas.santos@cidadao.es.gov.br',
        ];
        foreach ($forbiddenInLayout as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $appLayoutVue,
                "Atalho ou mock '{$forbidden}' ainda presente em AppLayout.vue"
            );
        }

        // Prontuario.vue, Oportunidades.vue, Carteira.vue & Atendimento.vue: sem PII fictício hardcoded
        $carteiraVue = file_get_contents(base_path('resources/js/Pages/Carteira.vue'));
        $atendimentoVue = file_get_contents(base_path('resources/js/Pages/Atendimento.vue'));
        $this->assertNotFalse($carteiraVue);
        $this->assertNotFalse($atendimentoVue);

        $this->assertStringNotContainsString('Lucas Santos de Oliveira', $prontuarioVue);
        $this->assertStringNotContainsString('***.192.830-**', $prontuarioVue);
        $this->assertStringNotContainsString('Construtora Capixaba S.A.', $oportunidadesVue);
        $this->assertStringNotContainsString('Lucas Santos de Oliveira', $carteiraVue);
        $this->assertStringNotContainsString('***.192.830-**', $carteiraVue);
        $this->assertStringNotContainsString('Lucas Santos de Oliveira', $atendimentoVue);
        $this->assertStringNotContainsString('Dra. Márcia Oliveira', $atendimentoVue);
        $this->assertStringNotContainsString('TCK-101', $atendimentoVue);
    }

    /**
     * 2. Verifica que o arquivo .env está configurado para prontidão de produção (APP_ENV=production, APP_DEBUG=false).
     */
    public function test_dotenv_is_configured_for_production_readiness(): void
    {
        $envPath = base_path('.env');
        $this->assertFileExists($envPath);

        $envContent = (string) file_get_contents($envPath);
        $this->assertMatchesRegularExpression('/^APP_ENV=production$/m', $envContent);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $envContent);
    }

    /**
     * 3. Em ambiente de produção (APP_ENV=production, APP_DEBUG=false):
     * - rotas de switch-role retornam 404;
     * - simulação Gov.br sem assinatura OAuth retorna 403;
     * - rotas internas redirecionam visitantes não autenticados para /login (incluindo / e /carteira/pdf);
     * - LgpdSecurityService rejeita CPFs de teste sem dígito verificador válido;
     * - usuários autenticados navegam sem erros.
     */
    public function test_production_environment_blocks_debug_endpoints_and_enforces_authentication(): void
    {
        $previousEnv = app()->environment();

        try {
            app()->detectEnvironment(fn () => 'production');
            config([
                'app.env' => 'production',
                'app.debug' => false,
                'services.govbr.client_secret' => '',
            ]);

            // Endpoints de troca rápida de perfil: CSRF ativo em produção (419 sem token) e 404 mesmo com CSRF válido
            $this->post('/auth/switch-role', ['role' => 'gestor'])->assertStatus(419);
            $this->withSession(['_token' => 'prod_csrf_token'])
                ->post('/auth/switch-role', ['role' => 'gestor', '_token' => 'prod_csrf_token'])
                ->assertStatus(404);
            $this->postJson('/api/auth/switch-role', ['role' => 'gestor'])->assertStatus(404);

            // Bypass Gov.br não assinado deve ser bloqueado (403) em produção
            $this->postJson('/api/auth/govbr/login', ['role' => 'gestor'])->assertStatus(403);

            // Rotas web protegidas devem redirecionar visitantes anônimos para /login
            foreach (['/', '/dashboard', '/oportunidades', '/prontuario', '/carteira', '/carteira/pdf'] as $protectedRoute) {
                $resp = $this->get($protectedRoute);
                $resp->assertStatus(302);
                $this->assertStringContainsString('/login', (string) $resp->headers->get('Location'));
            }

            // Validação estrita de CPF em produção bloqueia CPFs demo inválidos
            $lgpd = app(LgpdSecurityService::class);
            $this->assertFalse($lgpd->validateCpf('11122233344'));
            $this->assertTrue($lgpd->validateCpf('39053344705'));

            // Navegação autenticada em produção funciona normalmente sem erros 500
            $gestor = User::where('email', 'gestor@sejus.es.gov.br')->firstOrFail();
            $empregador = User::where('email', 'rh@parceirosejus.com.br')->firstOrFail();
            $roberto = User::where('email', 'roberto.fonseca@cidadao.es.gov.br')->firstOrFail();

            $this->actingAs($gestor)->get('/dashboard')->assertStatus(200);
            $this->actingAs($gestor)->get('/prontuario')->assertStatus(200);
            $this->actingAs($gestor)->get('/usuarios?q=gestor')->assertStatus(200);
            $this->actingAs($empregador)->get('/oportunidades/inserir-vaga')->assertStatus(200);

            // Carteira Digital renderiza dados reais do egresso autenticado e token HMAC válido
            $carteiraResp = $this->actingAs($roberto)->get('/carteira');
            $carteiraResp->assertStatus(200)
                ->assertInertia(function ($page) use (&$walletToken) {
                    $page->component('Carteira')
                        ->where('egresso.nome_completo', 'Roberto Fonseca da Silva');
                    $walletToken = $page->toArray()['props']['token'] ?? '';
                });

            $this->assertNotEmpty($walletToken);
            $this->getJson("/api/validar-carteira/{$walletToken}")
                ->assertStatus(200)
                ->assertJsonPath('valid', true);
        } finally {
            app()->detectEnvironment(fn () => $previousEnv);
            config(['app.env' => $previousEnv]);
        }
    }

    /**
     * 4. Endpoints sensíveis de API exigem autenticação (401) e RBAC estrito (403 para perfis não autorizados).
     */
    public function test_sensitive_api_endpoints_enforce_authentication_and_rbac(): void
    {
        // Visitante anônimo deve receber 401 em endpoints administrativos e clínicos
        $this->getJson('/api/users')->assertStatus(401);
        $this->postJson('/api/users', [])->assertStatus(401);
        $this->putJson('/api/users/1', ['name' => 'Invasor'])->assertStatus(401);
        $this->deleteJson('/api/users/1')->assertStatus(401);
        $this->getJson('/api/usuarios')->assertStatus(401);

        $this->getJson('/api/prontuarios/1')->assertStatus(401);
        $this->putJson('/api/prontuarios/1', ['situacao' => 'arquivado'])->assertStatus(401);
        $this->getJson('/api/prontuarios/1/timeline')->assertStatus(401);
        $this->postJson('/api/prontuarios/1/evolucao', ['descricao' => 'Nota falsa'])->assertStatus(401);
        $this->getJson('/api/candidaturas')->assertStatus(401);
        $this->postJson('/api/vagas/1/candidatar', ['egresso_id' => 1])->assertStatus(401);
        $this->postJson('/api/cursos', [])->assertStatus(401);

        // Empregador autenticado deve receber 403 em gestão de usuários, prontuários e candidaturas
        $empregador = User::where('email', 'rh@parceirosejus.com.br')->firstOrFail();
        $this->actingAs($empregador)->getJson('/api/users')->assertStatus(403);
        $this->actingAs($empregador)->postJson('/api/users', [])->assertStatus(403);
        $this->actingAs($empregador)->getJson('/api/prontuarios/1')->assertStatus(403);
        $this->actingAs($empregador)->getJson('/api/prontuarios/1/timeline')->assertStatus(403);
        $this->actingAs($empregador)->postJson('/api/prontuarios/1/evolucao', ['descricao' => 'Nota indevida'])->assertStatus(403);
        $this->actingAs($empregador)->getJson('/api/candidaturas')->assertStatus(403);
        $this->actingAs($empregador)->postJson('/api/cursos', [])->assertStatus(403);

        // Gestor autenticado acessa /api/users com filtro q normalmente
        $gestor = User::where('email', 'gestor@sejus.es.gov.br')->firstOrFail();
        $this->actingAs($gestor)
            ->getJson('/api/users?q=gestor')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    /**
     * 5. Assets compilados de produção (sem public/hot), meta csrf-token e sessão stateful nas rotas /api/*.
     */
    public function test_production_assets_csrf_meta_and_stateful_api_session_middleware(): void
    {
        $this->assertFileDoesNotExist(
            public_path('hot'),
            'O arquivo public/hot do servidor Vite HMR não deve existir em prontidão de produção.'
        );
        $this->assertFileExists(
            public_path('build/manifest.json'),
            'O manifesto compilado do Vite (public/build/manifest.json) deve existir para produção.'
        );

        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
        $html = $loginResponse->getContent();
        $this->assertStringContainsString('<meta name="csrf-token"', $html);
        $this->assertStringNotContainsString('localhost:5173', $html);
        $this->assertStringNotContainsString('@vite/client', $html);

        $apiMiddleware = app('router')->getMiddlewareGroups()['api'] ?? [];
        $this->assertContains(\Illuminate\Cookie\Middleware\EncryptCookies::class, $apiMiddleware);
        $this->assertContains(\Illuminate\Session\Middleware\StartSession::class, $apiMiddleware);
    }
}

