<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\CarteiraPdfController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AtendimentoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProntuarioController;
use App\Http\Controllers\VagaEmpregoController;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check() ? redirect('/dashboard') : redirect()->route('login');
});

// Public Digital Wallet Validation Routes
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validar'])->name('carteira.validar');
Route::get('/validar-carteira', [CarteiraValidationController::class, 'validarPublico'])->name('carteira.validar.publico');

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/auth/govbr/login', [AuthController::class, 'govbrLogin'])->name('auth.govbr.login');
Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch_role');

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Carteira PDF Export (Protected for authenticated users)
    Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');

    Route::get('/dashboard', function () {
        $totalEgressos = \App\Models\Egresso::count();
        $prontuariosAtivos = \App\Models\Prontuario::where('situacao', 'ativo')->count();

        $atendimentosHoje = \App\Models\VideoRoom::whereDate('created_at', today())->count()
            + \App\Models\ProntuarioTimeline::whereDate('data_evento', today())->count();
        $atendimentosTotal = \App\Models\VideoRoom::count()
            + \App\Models\ProntuarioTimeline::count();

        $vagasAbertas = \App\Models\VagaEmprego::where('status', 'aberta')->count();
        $vagasPreenchidas = (int) \App\Models\VagaEmprego::sum('vagas_preenchidas');
        $cursosAtivos = \App\Models\CursoCapacitacao::where('status', 'aberto')->count();

        // Taxa de acompanhamento ativo / não-reincidência calculada sobre prontuários
        $prontuariosTotal = \App\Models\Prontuario::count();
        $taxaReintegracao = $prontuariosTotal > 0
            ? round(($prontuariosAtivos / $prontuariosTotal) * 100, 1)
            : 0.0;

        $palette = ['#003366', '#0284c7', '#38bdf8', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#14b8a6'];
        $chartMunicipios = \App\Models\MunicipioEs::withCount('egressos')
            ->orderByDesc('total_egressos_atendidos')
            ->orderByDesc('egressos_count')
            ->limit(8)
            ->get()
            ->values()
            ->map(function ($m, $idx) use ($palette) {
                $val = max((int) $m->total_egressos_atendidos, (int) $m->egressos_count);
                $label = $m->tem_escritorio_fisico ? $m->nome : ($m->nome . '*');
                return [
                    'label' => $label,
                    'val' => $val,
                    'color' => $palette[$idx % count($palette)],
                ];
            })->all();

        $eixoTrabalho = \App\Models\ProntuarioTimeline::whereIn('tipo_evento', ['encaminhamento_vaga', 'emprego'])->count() + $vagasPreenchidas;
        $eixoCursos = \App\Models\ProntuarioTimeline::whereIn('tipo_evento', ['inscricao_curso', 'curso'])->count();
        $eixoPsico = \App\Models\ProntuarioTimeline::whereIn('tipo_evento', ['acolhimento_video', 'atendimento_remoto', 'atendimento_presencial', 'evolucao_psicossocial'])->count() + \App\Models\VideoRoom::count();
        $eixoDocs = \App\Models\ProntuarioTimeline::whereIn('tipo_evento', ['emissao_carteira', 'segunda_via_carteira', 'documento'])->count();
        $eixosTotal = max(1, $eixoTrabalho + $eixoCursos + $eixoPsico + $eixoDocs);

        $pctT = round($eixoTrabalho / $eixosTotal, 2);
        $pctC = round($eixoCursos / $eixosTotal, 2);
        $pctP = round($eixoPsico / $eixosTotal, 2);
        $pctD = round(max(0, 1 - ($pctT + $pctC + $pctP)), 2);

        $chartEixos = [
            ['label' => 'Emprego & Renda (' . round($pctT * 100) . '%)', 'val' => $pctT ?: 0.25, 'color' => '#10b981'],
            ['label' => 'Cursos & Capacitação (' . round($pctC * 100) . '%)', 'val' => $pctC ?: 0.25, 'color' => '#8b5cf6'],
            ['label' => 'Apoio Psicossocial (' . round($pctP * 100) . '%)', 'val' => $pctP ?: 0.25, 'color' => '#0284c7'],
            ['label' => 'Documentação Emitida (' . round($pctD * 100) . '%)', 'val' => $pctD ?: 0.25, 'color' => '#f59e0b'],
        ];

        $recentActivities = \App\Models\ProntuarioTimeline::with(['prontuario.egresso.municipio', 'responsavel'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function ($evt) {
                $iconType = 'prontuario';
                if (str_contains((string) $evt->tipo_evento, 'video') || str_contains((string) $evt->tipo_evento, 'atendimento')) {
                    $iconType = 'video';
                } elseif (str_contains((string) $evt->tipo_evento, 'vaga') || str_contains((string) $evt->tipo_evento, 'curso')) {
                    $iconType = 'vaga';
                } elseif (str_contains((string) $evt->tipo_evento, 'carteira') || str_contains((string) $evt->tipo_evento, 'documento')) {
                    $iconType = 'carteira';
                }

                return [
                    'id' => $evt->id,
                    'iconType' => $iconType,
                    'descricao' => $evt->titulo,
                    'municipio' => $evt->prontuario?->egresso?->municipio?->nome ?? 'Espírito Santo',
                    'autor' => $evt->responsavel?->name ?? 'Sistema Central SEJUS',
                    'tempo' => $evt->data_evento ? $evt->data_evento->diffForHumans() : 'Recente',
                ];
            })->all();

        $municipiosByMacro = [
            'metropolitana' => \App\Models\MunicipioEs::where('macrorregiao', 'like', '%Metropolitana%')->count(),
            'norte' => \App\Models\MunicipioEs::where('macrorregiao', 'like', '%Norte%')->count(),
            'sul' => \App\Models\MunicipioEs::where('macrorregiao', 'like', '%Sul%')->count(),
            'central' => \App\Models\MunicipioEs::where('macrorregiao', 'like', '%Central%')->count(),
        ];

        return Inertia::render('Dashboard', [
            'kpis' => [
                'total_egressos' => $totalEgressos,
                'atendimentos_hoje' => $atendimentosHoje,
                'atendimentos_total' => $atendimentosTotal,
                'vagas_preenchidas' => $vagasPreenchidas,
                'taxa_reincidencia_zero' => $taxaReintegracao,
                'vagas_abertas' => $vagasAbertas,
                'cursos_ativos' => $cursosAtivos,
            ],
            'chartMunicipios' => $chartMunicipios,
            'chartEixos' => $chartEixos,
            'recentActivities' => $recentActivities,
            'macroCounts' => $municipiosByMacro,
        ]);
    })->name('dashboard');

    Route::get('/atendimento', [AtendimentoController::class, 'indexView'])
        ->middleware('role:gestor,tecnico,egresso,familiar,suporte')->name('atendimento');

    Route::get('/oportunidades', [VagaEmpregoController::class, 'indexView'])
        ->name('oportunidades');

    Route::get('/oportunidades/inserir-vaga', [VagaEmpregoController::class, 'indexView'])
        ->middleware('role:empregador,gestor,suporte,tecnico')
        ->name('oportunidades.inserir_vaga');

    Route::get('/vagas/modelo-csv', [VagaEmpregoController::class, 'downloadModeloCsv'])
        ->middleware('role:empregador,gestor,suporte,tecnico')
        ->name('vagas.modelo_csv');

    Route::post('/vagas/importar-csv', [VagaEmpregoController::class, 'importarCsv'])
        ->middleware('role:empregador,gestor,suporte,tecnico')
        ->name('vagas.importar_csv');

    Route::get('/carteira', [CarteiraController::class, 'showView'])
        ->middleware('role:gestor,tecnico,egresso,suporte')
        ->name('carteira');

    Route::get('/geolocalizacao', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $isStaff = $user && in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);

        $municipios = \App\Models\MunicipioEs::withCount([
            'egressos as egresso_records_count',
            'vagas as total_vagas_abertas' => fn ($q) => $q->where('status', 'aberta'),
            'redeApoio as total_unidades_apoio' => fn ($q) => $q->where('ativo', true),
        ])->orderBy('nome')->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'codigo_ibge' => (string) $m->codigo_ibge,
                'nome' => $m->nome,
                'microrregiao' => $m->microrregiao ?? 'Espírito Santo',
                'macrorregiao' => $m->macrorregiao ?? 'Central',
                'tem_escritorio_fisico' => (bool) $m->tem_escritorio_fisico,
                'total_egressos' => max((int) $m->total_egressos_atendidos, (int) $m->egresso_records_count),
                'populacao' => (int) ($m->populacao_estimada ?? 0),
                'total_vagas_abertas' => (int) $m->total_vagas_abertas,
                'total_unidades_apoio' => (int) $m->total_unidades_apoio,
                'latitude' => (float) ($m->latitude ?? -20.3155),
                'longitude' => (float) ($m->longitude ?? -40.3128),
            ];
        })->values()->all();

        $redeApoio = \App\Models\RedeApoio::where('ativo', true)
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'municipio_id' => $u->municipio_id,
                'tipo' => $u->tipo,
                'nome' => $u->nome,
                'endereco' => $u->endereco,
                'telefone' => $u->telefone ?? 'Não informado',
                'email' => $u->email,
                'horario' => $u->horario_funcionamento ?? '08h às 17h',
                'servicos_oferecidos' => $u->servicos_oferecidos ?? [],
            ])->values()->all();

        $egressosList = $isStaff
            ? \App\Models\Egresso::with('prontuario:id,egresso_id,numero_prontuario')
                ->orderBy('nome_completo')
                ->limit(100)
                ->get(['id', 'nome_completo'])
                ->filter(fn ($e) => $e->prontuario !== null)
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => $e->nome_completo,
                    'prontuario_id' => $e->prontuario->id,
                    'numero_prontuario' => $e->prontuario->numero_prontuario,
                ])->values()->all()
            : [];

        return Inertia::render('Geolocalizacao', [
            'municipios' => $municipios,
            'redeApoio' => $redeApoio,
            'egressosList' => $egressosList,
        ]);
    })->name('geolocalizacao');

    Route::get('/prontuario/{id?}', [ProntuarioController::class, 'showView'])
        ->middleware('role:gestor,tecnico,egresso,suporte')
        ->name('prontuario');

    Route::get('/relatorios', function (\Illuminate\Http\Request $request) {
        $period = $request->query('period', 'ano_2026');
        $region = $request->query('region', 'todas');
        $axis = $request->query('axis', 'todos');

        $since = match ($period) {
            'mes_atual' => now()->startOfMonth(),
            'trimestre' => now()->subDays(90),
            'ano_2026' => now()->startOfYear(),
            default => null,
        };

        $auditLogs = \App\Models\ProntuarioAuditLog::with('user.perfil')
            ->when($since, fn ($q) => $q->where('timestamp', '>=', $since))
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'timestamp' => $log->timestamp ? $log->timestamp->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'acao' => $log->acao,
                'user_name' => $log->user?->name ?? 'Sistema Central SEJUS',
                'user_role' => $log->user?->perfil?->nome ?? 'Sistema / Externo',
                'ip' => $log->ip_address ?? '127.0.0.1',
                'current_hash' => $log->current_hash ?? hash('sha256', (string) $log->id),
            ])->values()->all();

        $timelineCountsByMun = \Illuminate\Support\Facades\DB::table('prontuario_timeline as pt')
            ->join('prontuarios as p', 'p.id', '=', 'pt.prontuario_id')
            ->join('egressos as e', 'e.id', '=', 'p.egresso_id')
            ->when($since, fn ($q) => $q->where('pt.data_evento', '>=', $since))
            ->when($axis === 'trabalho', fn ($q) => $q->whereIn('pt.tipo_evento', ['encaminhamento_vaga', 'emprego']))
            ->when($axis === 'cursos', fn ($q) => $q->whereIn('pt.tipo_evento', ['inscricao_curso', 'curso']))
            ->when($axis === 'psicossocial', fn ($q) => $q->whereIn('pt.tipo_evento', ['acolhimento_video', 'atendimento_remoto', 'atendimento_presencial', 'evolucao_psicossocial']))
            ->when($axis === 'documentos', fn ($q) => $q->whereIn('pt.tipo_evento', ['emissao_carteira', 'segunda_via_carteira', 'documento']))
            ->selectRaw('e.municipio_residencia_id, COUNT(*) as total')
            ->groupBy('e.municipio_residencia_id')
            ->pluck('total', 'municipio_residencia_id');

        $vagasByMun = \Illuminate\Support\Facades\DB::table('vagas_emprego')
            ->selectRaw('municipio_id, COALESCE(SUM(vagas_preenchidas), 0) as preenchidas, COUNT(*) as total_vagas')
            ->groupBy('municipio_id')
            ->get()
            ->keyBy('municipio_id');

        $municipiosQuery = \App\Models\MunicipioEs::withCount('egressos')
            ->when($region !== 'todas', function ($q) use ($region) {
                $q->whereRaw('LOWER(macrorregiao) LIKE ?', ['%' . strtolower($region) . '%']);
            })
            ->orderByDesc('egressos_count')
            ->orderByDesc('total_egressos_atendidos');

        $municipiosSummary = $municipiosQuery->limit(15)->get()->map(function ($m) use ($timelineCountsByMun, $vagasByMun) {
            $egressosCount = max((int) $m->egressos_count, (int) $m->total_egressos_atendidos);
            $atendimentosReal = (int) ($timelineCountsByMun[$m->id] ?? 0);
            $vagasInfo = $vagasByMun[$m->id] ?? null;

            return [
                'municipio' => $m->nome,
                'regiao' => $m->macrorregiao ?? 'Espírito Santo',
                'egressos' => $egressosCount,
                'atendimentos' => $atendimentosReal,
                'vagas' => $vagasInfo ? (int) $vagasInfo->preenchidas : 0,
                'fisico' => (bool) $m->tem_escritorio_fisico,
            ];
        })->values()->all();

        $videoCount = \App\Models\VideoRoom::when($since, fn ($q) => $q->where('created_at', '>=', $since))->count();
        $timelineTotal = (int) $timelineCountsByMun->sum();
        $totalAtendimentos = $timelineTotal + ($axis === 'todos' || $axis === 'psicossocial' ? $videoCount : 0);

        $videoTimelineCount = \App\Models\ProntuarioTimeline::whereIn('tipo_evento', ['acolhimento_video', 'atendimento_remoto'])
            ->when($since, fn ($q) => $q->where('data_evento', '>=', $since))
            ->count() + $videoCount;
        $taxaVideoPct = $totalAtendimentos > 0 ? round(($videoTimelineCount / $totalAtendimentos) * 100, 1) : 0.0;

        $avgDurationSec = (int) (\App\Models\VideoAttendee::when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds') ?: 0);

        $vagasPreenchidasTotal = (int) \App\Models\VagaEmprego::sum('vagas_preenchidas');
        $prontTotal = \App\Models\Prontuario::count();
        $prontAtivos = \App\Models\Prontuario::where('situacao', 'ativo')->count();
        $cidadesAtendidas = \App\Models\MunicipioEs::whereHas('egressos')
            ->orWhere('total_egressos_atendidos', '>', 0)
            ->count();

        $audit = app(\App\Services\AuditService::class);

        return Inertia::render('Relatorios', [
            'auditLogs' => $auditLogs,
            'regionalSummary' => $municipiosSummary,
            'chainIntegrity' => $audit->verifyChainIntegrity(),
            'filters' => [
                'period' => $period,
                'region' => $region,
                'axis' => $axis,
            ],
            'kpis' => [
                'total_atendimentos' => $totalAtendimentos,
                'taxa_video_pct' => $taxaVideoPct,
                'duracao_media_min' => (int) round($avgDurationSec / 60),
                'vagas_preenchidas' => $vagasPreenchidasTotal,
                'taxa_reincidencia_zero' => $prontTotal > 0 ? round(($prontAtivos / $prontTotal) * 100, 1) : 0.0,
                'cidades_atendidas' => $cidadesAtendidas,
            ],
        ]);
    })->middleware('role:gestor,tecnico,suporte')->name('relatorios');

    Route::get('/seguranca-lgpd', function () {
        $audit = app(\App\Services\AuditService::class);
        return Inertia::render('SegurancaLgpd', [
            'chainIntegrity' => $audit->verifyChainIntegrity(),
            'totalAuditLogs' => \App\Models\ProntuarioAuditLog::count(),
        ]);
    })->name('seguranca.lgpd');

    Route::get('/catalogo-temas', fn() => Inertia::render('CatalogoTemas'))->name('catalogo.temas');
});

// User Management Routes (Gestor & Suporte)
Route::middleware(['auth', 'role:gestor,suporte'])->group(function () {
    Route::get('/usuarios', [UserController::class, 'indexView'])->name('usuarios.index');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    Route::post('/usuarios/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('usuarios.toggle');
});


