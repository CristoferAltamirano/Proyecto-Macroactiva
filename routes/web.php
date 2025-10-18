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
use App\Http\Controllers\PlanCuentasController;
use App\Http\Controllers\LibroController;
use App\Http\Controllers\PagoOnlineController;

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

    // Rutas para Contabilidad
    Route::prefix('contabilidad')->name('contabilidad.')->group(function () {
        Route::resource('cuentas', PlanCuentasController::class);
        Route::post('cuentas/import', [PlanCuentasController::class, 'importCsv'])->name('cuentas.import');
        Route::get('cuentas/export', [PlanCuentasController::class, 'exportCsv'])->name('cuentas.export');
        Route::get('libro', [LibroController::class, 'index'])->name('libro.index');
        Route::get('libro/export', [LibroController::class, 'exportCsv'])->name('libro.export');
    });
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
        Route::get('/dashboard', [App\Http\Controllers\PortalResidenteController::class, 'dashboard'])->name('dashboard');
        Route::get('/cobro/{cobro}', [App\Http\Controllers\PortalResidenteController::class, 'showCobro'])->name('cobro.show');
        Route::get('/cobro/{cobro}/pdf', [App\Http\Controllers\PortalResidenteController::class, 'descargarBoletaPDF'])->name('cobro.pdf');

        // Rutas para Pagos Online
        Route::post('/pago/iniciar/{cobro}', [PagoOnlineController::class, 'iniciar'])->name('pago.iniciar');
    });

    // Ruta de confirmación de Webpay (puede ser visitada sin sesión activa)
    Route::get('/pago/confirmar', [PagoOnlineController::class, 'confirmar'])->name('pago.confirmar');
});