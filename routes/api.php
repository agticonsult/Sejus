<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProntuarioController;
use App\Http\Controllers\ProntuarioTimelineController;
use App\Http\Controllers\VagaEmpregoController;
use App\Http\Controllers\CursoCapacitacaoController;
use App\Http\Controllers\CandidaturaController;
use App\Http\Controllers\TerritorioController;
use App\Http\Controllers\RedeApoioController;
use App\Http\Controllers\KpiDashboardController;
use App\Http\Controllers\WebRtcTokenController;
use App\Http\Controllers\WebRtcWebhookController;
use App\Http\Controllers\AtendimentoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EgressoConhecimentoController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'app' => config('app.name', 'Conecta Egresso'),
    ]);
});

// Digital Wallet QR Code Verification
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validarApi']);

// WebRTC Webhook Ingestion (HMAC-SHA256 Signed by Python FastAPI Microservice)
Route::post('/webhooks/webrtc', [WebRtcWebhookController::class, 'handle'])->name('api.webhooks.webrtc');

// Authentication & SSO Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/govbr/login', [AuthController::class, 'govbrLogin'])->name('api.auth.govbr.login');
    Route::post('/switch-role', [AuthController::class, 'switchRole'])->name('api.auth.switch_role');
});

/*
|--------------------------------------------------------------------------
| Authenticated API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth.mobile:optional'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth.mobile')->name('api.auth.me');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth.mobile')->name('api.auth.refresh');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Carteira Digital do Egresso (Mobile & Web)
    Route::get('/carteira', [CarteiraController::class, 'show'])->middleware('auth.mobile')->name('api.carteira');
    Route::get('/carteira/digital', [CarteiraController::class, 'show'])->middleware('auth.mobile')->name('api.carteira.digital');
    Route::post('/carteira/segunda-via', [CarteiraController::class, 'solicitarSegundaVia'])->name('api.carteira.segunda_via');

    // Canal DPO LGPD (Solicitações do Titular persistidas na trilha de auditoria)
    Route::post('/lgpd/solicitacao', function (\Illuminate\Http\Request $request) {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }
        $validated = $request->validate([
            'tipo' => 'required|string|in:confirmacao,acesso,retificacao,revogacao',
            'detalhes' => 'required|string|max:2000',
        ]);
        $log = app(\App\Services\AuditService::class)->log(
            $user->egresso?->prontuario?->id,
            'DPO_SOLICITACAO_TITULAR',
            [
                'tipo_direito' => $validated['tipo'],
                'detalhes' => trim($validated['detalhes']),
                'titular_nome' => $user->name,
            ],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );
        $protocolo = 'DPO-' . now()->format('Y') . '-' . str_pad((string) $log->id, 6, '0', STR_PAD_LEFT);
        return response()->json([
            'status' => 'created',
            'protocolo' => $protocolo,
            'message' => "Solicitação protocolada com sucesso junto ao DPO da SEJUS/ES.\nProtocolo: {$protocolo}",
        ], 201);
    })->name('api.lgpd.solicitacao');

    // WebRTC Signaling Room Token Generation & Real Virtual Queue
    Route::post('/webrtc/token', [WebRtcTokenController::class, 'generateToken'])->name('api.webrtc.token');
    Route::prefix('atendimento')->group(function () {
        Route::get('/fila', [AtendimentoController::class, 'fila'])->name('api.atendimento.fila');
        Route::post('/fila/entrar', [AtendimentoController::class, 'entrarFila'])->name('api.atendimento.entrar');
        Route::post('/fila/{id}/chamar', [AtendimentoController::class, 'chamar'])->middleware('role:gestor,tecnico,suporte')->name('api.atendimento.chamar');
        Route::post('/fila/{id}/encerrar', [AtendimentoController::class, 'encerrar'])->name('api.atendimento.encerrar');
    });

    // Catálogo de Conhecimentos e Qualificações Profissionais (Milestone 1)
    Route::get('conhecimentos', [EgressoConhecimentoController::class, 'indexCatalog'])
        ->name('api.conhecimentos.catalog');

    // Conhecimentos vinculados à Ficha do Egresso
    Route::get('egressos/{egresso}/conhecimentos', [EgressoConhecimentoController::class, 'index'])
        ->name('api.egressos.conhecimentos.index');
    Route::put('egressos/{egresso}/conhecimentos', [EgressoConhecimentoController::class, 'sync'])
        ->name('api.egressos.conhecimentos.sync');
    Route::post('egressos/{egresso}/conhecimentos', [EgressoConhecimentoController::class, 'sync'])
        ->name('api.egressos.conhecimentos.sync.post');

    // Prontuário Único & Timeline
    Route::apiResource('prontuarios', ProntuarioController::class);
    Route::get('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'index'])->name('api.prontuarios.timeline.index');
    Route::post('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'store'])->name('api.prontuarios.timeline.store');
    Route::post('prontuarios/{prontuario}/evolucao', [ProntuarioTimelineController::class, 'storeEvolucao'])->name('api.prontuarios.evolucao');

    // Vagas de Emprego, Modelo/Importação CSV & Candidaturas
    Route::get('vagas/modelo-csv', [VagaEmpregoController::class, 'downloadModeloCsv'])
        ->name('api.vagas.modelo_csv');
    Route::post('vagas/importar-csv', [VagaEmpregoController::class, 'importarCsv'])
        ->name('api.vagas.importar_csv');
    Route::apiResource('vagas', VagaEmpregoController::class);
    Route::post('vagas/{vaga}/candidatar', [VagaEmpregoController::class, 'candidatar'])->name('api.vagas.candidatar');
    Route::apiResource('candidaturas', CandidaturaController::class)->only(['index', 'store', 'show']);

    // Cursos de Capacitação
    Route::apiResource('cursos', CursoCapacitacaoController::class);
    Route::post('cursos/{curso}/inscrever', [CursoCapacitacaoController::class, 'inscrever'])->name('api.cursos.inscrever');

    // Territorial Mapping & Rede de Apoio
    Route::get('territorios', [TerritorioController::class, 'index'])->name('api.territorios.index');
    Route::get('territorios/regioes', [TerritorioController::class, 'regioes'])->name('api.territorios.regioes');
    Route::post('territorios/checkin-gps', [TerritorioController::class, 'checkinGps'])->name('api.territorios.checkin_gps');
    Route::get('territorios/{codigo_ibge_or_id}', [TerritorioController::class, 'show'])->name('api.territorios.show');
    Route::get('municipios', [TerritorioController::class, 'index'])->name('api.municipios.index');
    Route::apiResource('rede-apoio', RedeApoioController::class);

    // User Management API Resource (Strict Gestor / Suporte RBAC)
    Route::middleware(['role:gestor,suporte'])->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('usuarios', UserController::class)->names('api.usuarios');
    });

    // Management KPIs & Analytics (Protected for Staff)
    Route::prefix('kpis')->middleware(['role:gestor,tecnico,suporte'])->group(function () {
        Route::get('dashboard', [KpiDashboardController::class, 'dashboard'])->name('api.kpis.dashboard');
        Route::get('regional', [KpiDashboardController::class, 'regional'])->name('api.kpis.regional');
        Route::get('timeline', [KpiDashboardController::class, 'timeline'])->name('api.kpis.timeline');
        Route::get('telemetria', [KpiDashboardController::class, 'telemetria'])->name('api.kpis.telemetria');
    });

    // Google Meet API Routes (Milestone 3 - Strict Staff RBAC Protection)
    Route::prefix('meet')->name('api.meet.')->middleware(['role:gestor,tecnico,suporte'])->group(function () {
        Route::post('/rooms', [\App\Http\Controllers\Api\GoogleMeetController::class, 'createRoom'])->name('rooms.create');
        Route::get('/rooms/{id}', [\App\Http\Controllers\Api\GoogleMeetController::class, 'getRoom'])->where('id', '.*')->name('rooms.show');
        Route::post('/conferences/sync', [\App\Http\Controllers\Api\GoogleMeetController::class, 'syncConference'])->name('conferences.sync');
        Route::get('/conferences/{id}', [\App\Http\Controllers\Api\GoogleMeetController::class, 'getConference'])->where('id', '.*')->name('conferences.show');
    });
});
