<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\CarteiraPdfController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Rotas Web Reativas do Inertia.js
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/atendimento', function () {
    return Inertia::render('Atendimento');
})->name('atendimento');

Route::get('/oportunidades', function () {
    return Inertia::render('Oportunidades');
})->name('oportunidades');

Route::get('/carteira', function () {
    return Inertia::render('Carteira');
})->name('carteira');

Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');

Route::get('/geolocalizacao', function () {
    return Inertia::render('Geolocalizacao');
})->name('geolocalizacao');

Route::get('/prontuario/{id?}', function () {
    return Inertia::render('Prontuario');
})->name('prontuario');

Route::get('/relatorios', function () {
    return Inertia::render('Relatorios');
})->name('relatorios');

Route::get('/seguranca-lgpd', function () {
    return Inertia::render('SegurancaLgpd');
})->name('seguranca.lgpd');

// Public Digital Wallet Validation Route
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validar'])->name('carteira.validar');
Route::get('/validar-carteira', [CarteiraValidationController::class, 'validarPublico'])->name('carteira.validar.publico');

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/auth/govbr/login', [AuthController::class, 'govbrLogin'])->name('auth.govbr.login');
Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch_role');

// User Management Routes (Gestor & Suporte)
Route::middleware(['auth', 'role:gestor,suporte'])->group(function () {
    Route::get('/usuarios', [UserController::class, 'indexView'])->name('usuarios.index');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    Route::post('/usuarios/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('usuarios.toggle');
});

