<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\CarteiraPdfController;
use App\Http\Controllers\AuthController;
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

// Carteira PDF Export (Controller enforces authentication in production)
Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/auth/govbr/login', [AuthController::class, 'govbrLogin'])->name('auth.govbr.login');
Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch_role');

// Authenticated Application Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/atendimento', function () {
        return Inertia::render('Atendimento');
    })->middleware('role:gestor,tecnico,egresso,familiar,suporte')->name('atendimento');

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
        return Inertia::render('Geolocalizacao');
    })->name('geolocalizacao');

    Route::get('/prontuario/{id?}', [ProntuarioController::class, 'showView'])
        ->middleware('role:gestor,tecnico,egresso,suporte')
        ->name('prontuario');

    Route::get('/relatorios', function () {
        $auditLogs = \App\Models\ProntuarioAuditLog::with('user.perfil')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'timestamp' => $log->timestamp ? $log->timestamp->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'acao' => $log->acao,
                'user_name' => $log->user?->name ?? 'Sistema Central SEJUS',
                'user_role' => $log->user?->perfil?->nome ?? 'Sistema / Externo',
                'ip' => $log->ip_address ?? '127.0.0.1',
                'current_hash' => $log->current_hash ?? hash('sha256', (string) $log->id),
            ]);

        $municipiosSummary = \App\Models\MunicipioEs::orderBy('populacao_estimada', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($m) => [
                'municipio' => $m->nome,
                'regiao' => $m->macrorregiao ?? 'Espírito Santo',
                'egressos' => $m->total_egressos_atendidos ?? 0,
                'atendimentos' => ($m->total_egressos_atendidos ?? 0) * 8,
                'vagas' => (int) (($m->populacao_estimada ?? 50000) / 1000),
                'fisico' => (bool) $m->tem_escritorio_fisico,
            ]);

        return Inertia::render('Relatorios', [
            'auditLogs' => $auditLogs,
            'regionalSummary' => $municipiosSummary,
        ]);
    })->middleware('role:gestor,tecnico,suporte')->name('relatorios');

    Route::get('/seguranca-lgpd', function () {
        return Inertia::render('SegurancaLgpd');
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


