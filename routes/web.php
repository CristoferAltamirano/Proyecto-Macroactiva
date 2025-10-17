<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\GeneracionCobroController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\ResidenteLoginController; // <-- NUEVO

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

    // Rutas de Reportes
    Route::get('/reportes/morosidad', [ReportesController::class, 'morosidad'])->name('reportes.morosidad');
    Route::get('/reportes/gastos', [ReportesController::class, 'gastosMensuales'])->name('reportes.gastos');
});

/*
|--------------------------------------------------------------------------
| Rutas del Portal de Residentes
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->group(function () {
    // Muestra el formulario de login del residente
    Route::get('/login', [ResidenteLoginController::class, 'showLoginForm'])->name('login');
    // Procesa el login del residente
    Route::post('/login', [ResidenteLoginController::class, 'login'])->name('login.submit');
    // Cierra la sesión del residente
    Route::post('/logout', [ResidenteLoginController::class, 'logout'])->name('logout');

    // Rutas protegidas para residentes logueados
    Route::middleware('auth:residente')->group(function () {
        // Aún no creamos esta ruta, pero ya la dejamos definida.
        Route::get('/dashboard', function () {
            return '¡Bienvenido al portal de residentes!';
        })->name('dashboard');
    });
});