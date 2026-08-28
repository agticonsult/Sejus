<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraValidationController;
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
use App\Http\Controllers\UserController;

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

Route::middleware(['web'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // WebRTC Signaling Room Token Generation
    Route::post('/webrtc/token', [WebRtcTokenController::class, 'generateToken'])->name('api.webrtc.token');

    // Prontuário Único & Timeline
    Route::apiResource('prontuarios', ProntuarioController::class);
    Route::get('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'index'])->name('api.prontuarios.timeline.index');
    Route::post('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'store'])->name('api.prontuarios.timeline.store');
    Route::post('prontuarios/{prontuario}/evolucao', [ProntuarioTimelineController::class, 'storeEvolucao'])->name('api.prontuarios.evolucao');

    // Vagas de Emprego & Candidaturas
    Route::apiResource('vagas', VagaEmpregoController::class);
    Route::post('vagas/{vaga}/candidatar', [VagaEmpregoController::class, 'candidatar'])->name('api.vagas.candidatar');
    Route::apiResource('candidaturas', CandidaturaController::class)->only(['index', 'store', 'show']);

    // Cursos de Capacitação
    Route::apiResource('cursos', CursoCapacitacaoController::class);
    Route::post('cursos/{curso}/inscrever', [CursoCapacitacaoController::class, 'inscrever'])->name('api.cursos.inscrever');

    // Territorial Mapping & Rede de Apoio
    Route::get('territorios', [TerritorioController::class, 'index'])->name('api.territorios.index');
    Route::get('territorios/regioes', [TerritorioController::class, 'regioes'])->name('api.territorios.regioes');
    Route::get('territorios/{codigo_ibge_or_id}', [TerritorioController::class, 'show'])->name('api.territorios.show');
    Route::get('municipios', [TerritorioController::class, 'index'])->name('api.municipios.index');
    Route::apiResource('rede-apoio', RedeApoioController::class);

    // User Management API Resource
    Route::apiResource('users', UserController::class);
    Route::apiResource('usuarios', UserController::class);

    // Management KPIs & Analytics
    Route::prefix('kpis')->group(function () {
        Route::get('dashboard', [KpiDashboardController::class, 'dashboard'])->name('api.kpis.dashboard');
        Route::get('regional', [KpiDashboardController::class, 'regional'])->name('api.kpis.regional');
        Route::get('timeline', [KpiDashboardController::class, 'timeline'])->name('api.kpis.timeline');
        Route::get('telemetria', [KpiDashboardController::class, 'telemetria'])->name('api.kpis.telemetria');
    });
});
