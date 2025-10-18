<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\GeneracionCobroController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\ResidenteLoginController;
use App\Http\Controllers\ReporteController; // <-- NUEVO

/*
|--------------------------------------------------------------------------
| Rutas del Panel de Administración
|--------------------------------------------------------------------------
*/
// Login de Administradores
Route::get('/', [HomeController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.do');

// Rutas protegidas para Admins
Route::middleware('auth')->group(function () {
    Route::get('/panel', [PanelController::class, 'index'])->name('panel');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    
    Route::resource('unidades', UnidadController::class);
    Route::resource('gastos', GastoController::class);
    
    Route::get('/generacion', [GeneracionCobroController::class, 'index'])->name('generacion.index');
    Route::post('/generacion', [GeneracionCobroController::class, 'generar'])->name('generacion.generar');

    Route::get('/cobros', [CobroController::class, 'index'])->name('cobros.index');
    Route::patch('/cobros/{cobro}/pagar', [CobroController::class, 'registrarPago'])->name('cobros.pagar');

    // --- NUEVAS RUTAS PARA REPORTES ---
    Route::get('/reportes/morosidad', [ReporteController::class, 'morosidad'])->name('reportes.morosidad');
    Route::get('/reportes/gastos', [ReporteController::class, 'gastosMensuales'])->name('reportes.gastos');
});

/*
|--------------------------------------------------------------------------
| Rutas del Portal de Residentes
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', [ResidenteLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [ResidenteLoginController::class, 'login'])->name('login.submit');
    Route::post('/logout', [ResidenteLoginController::class, 'logout'])->name('logout');

    Route::middleware('auth:residente')->group(function () {
        Route::get('/dashboard', function () {
            return '¡Bienvenido al portal de residentes!';
        })->name('dashboard');
    });
});