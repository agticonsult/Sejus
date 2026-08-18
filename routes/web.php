<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Public Digital Wallet Validation Route
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validar'])->name('carteira.validar');
Route::get('/validar-carteira', [CarteiraValidationController::class, 'validarPublico'])->name('carteira.validar.publico');

// Authentication Routes
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/auth/govbr/login', [AuthController::class, 'govbrLogin'])->name('auth.govbr.login');
Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch_role');
