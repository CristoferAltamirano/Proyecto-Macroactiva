<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalResidenteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\WebpayController;
use App\Http\Controllers\CierreMesController;
use App\Http\Controllers\AdminCobroController;
use App\Http\Controllers\AvisoCobroController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProrrateoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\MaestrosCondoController;
use App\Http\Controllers\CondominioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\TrabajadorController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\RemuneracionController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\FondoReservaController as FRController;
use App\Http\Controllers\ResidenciaController;
use App\Http\Controllers\CopropietarioController;
use App\Http\Controllers\CargoManualController;
use App\Http\Controllers\AdminUserCondoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportesController;
/* NUEVOS */
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\ConciliacionController;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\CierreAnualController;
use App\Http\Controllers\PlanCuentasController;
use App\Http\Controllers\LibroController;

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\NoCacheMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\EnsureCondoContext; // fija/normaliza ctx de condominio
use App\Http\Middleware\CondoAccess;        // valida acceso al ctx

// 🔒 SIN KERNEL: middlewares por clase
use App\Http\Middleware\AdminIpAllowlist;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\MaxPostSize;

/* Home */
Route::get('/', fn() => redirect()->route('panel'))
    ->middleware(['auth', SecurityHeaders::class, ForceHttps::class])
    ->name('home');

/* Login / Logout */
Route::middleware([NoCacheMiddleware::class, SecurityHeaders::class, ForceHttps::class])->group(function () {
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

        // Rate limit login desde config/security.php
        Route::post('/login', [AuthController::class, 'doLogin'])
            ->middleware([
                'throttle:' . config('security.rate_limits.login_per_min', 8) . ',1',
                MaxPostSize::class
            ])
            ->name('login.do');

        /* Reset por email (rate-limit desde config/security.php) */
        Route::get('/forgot', [PasswordResetController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot', [PasswordResetController::class, 'sendLink'])
            ->middleware('throttle:' . config('security.rate_limits.password_email_per_min', 5) . ',1')
            ->name('password.email');

        Route::get('/reset', [PasswordResetController::class, 'showReset'])->name('password.reset.form');
        Route::post('/reset', [PasswordResetController::class, 'doReset'])
            ->middleware('throttle:' . config('security.rate_limits.password_email_per_min', 5) . ',1')
            ->name('password.reset');
    });
});
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', SecurityHeaders::class, ForceHttps::class])
    ->name('logout');

/* Panel (con contexto y control de acceso en orden correcto) */
Route::get('/panel', [DashboardController::class, 'panel'])
    ->middleware([
        'auth',
        EnsureCondoContext::class, // 1) asegura ctx
        CondoAccess::class,        // 2) valida permiso sobre el ctx resuelto
        NoCacheMiddleware::class,
        SecurityHeaders::class,
        ForceHttps::class,
    ])
    ->name('panel');

/* Selector de condominio: solo super_admin */
Route::post('/ctx/condominio', [ContextController::class, 'setCondominio'])
    ->middleware(['auth', RoleMiddleware::class . ':super_admin', SecurityHeaders::class, ForceHttps::class, MaxPostSize::class])
    ->name('ctx.condo.set');

/* ============================
   LIBRO (auth + roles, SIN ctx)
   ============================ */
Route::middleware([
    'auth',
    AdminIpAllowlist::class, // 🔒 IP allowlist solo en /admin/*
    // Permitimos varios alias de admin de condominio
    RoleMiddleware::class . ':super_admin,admin,administrador,admin_condominio,condo_admin',
    SecurityHeaders::class,
    ForceHttps::class,
])->group(function () {
    Route::get('/admin/libro', [LibroController::class, 'index'])->name('admin.libro.panel');

    // Export con rate limit configurable
    Route::get('/admin/libro/export.csv', [LibroController::class, 'exportCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.libro.export.csv');
});

/* ==========================================================
   Admin (super_admin + admin) – orden: auth → EnsureCtx → CondoAccess → Role
   ========================================================== */
Route::middleware([
    'auth',
    EnsureCondoContext::class,              // primero fija el contexto
    CondoAccess::class,                     // luego valida acceso al contexto
    AdminIpAllowlist::class,                // 🔒 SIN Kernel: FQN directo
    RoleMiddleware::class . ':super_admin,admin',
    SecurityHeaders::class,
    ForceHttps::class,
    MaxPostSize::class,
])->group(function () {

    /* Pagos */
    Route::get('/pagos/panel', [PagoController::class, 'panel'])->name('pagos.panel');
    Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::get('/pagos/{idPago}/aprobar-demo', [PagoController::class, 'aprobarDemo'])->name('pagos.aprobar.demo');

    /* Cierres mensual */
    Route::get('/cierres/panel',  [CierreMesController::class, 'panel'])->name('cierres.panel');
    Route::post('/cierres/cerrar', [CierreMesController::class, 'cerrar'])->name('cierres.cerrar');
    Route::post('/cierres/reabrir', [CierreMesController::class, 'reabrir'])->name('cierres.reabrir');
    Route::get('/cierres/status', [CierreMesController::class, 'status'])->name('cierres.status');
    Route::get('/cierres/pdf',    [CierreMesController::class, 'pdf'])->name('cierres.pdf');
    Route::get('/cierres/diff',   [CierreMesController::class, 'diff'])->name('cierres.diff');

    /* Cierre anual */
    Route::post('/cierres/cerrar-anual', [CierreAnualController::class, 'cerrar'])->name('cierres.cerrar.anual');
    Route::post('/cierres/reabrir-anual', [CierreAnualController::class, 'reabrir'])->name('cierres.reabrir.anual');

    /* Cobros */
    Route::get('/admin/cobros', [AdminCobroController::class, 'panel'])->name('admin.cobros.panel');
    Route::post('/admin/cobros/generar', [AdminCobroController::class, 'generar'])->name('admin.cobros.generar');
    Route::post('/admin/cobros/intereses', [AdminCobroController::class, 'intereses'])->name('admin.cobros.intereses');

    /* Avisos */
    Route::get('/admin/avisos', [AvisoCobroController::class, 'panel'])->name('admin.avisos.panel');
    Route::post('/admin/avisos/enviar', [AvisoCobroController::class, 'enviar'])->name('admin.avisos.enviar');

    /* Conciliación */
    Route::get('/admin/conciliacion', [ConciliacionController::class, 'panel'])->name('admin.conciliacion.panel');
    Route::post('/admin/conciliacion/upload', [ConciliacionController::class, 'upload'])->name('admin.conciliacion.upload');
    Route::get('/admin/conciliacion/{id}', [ConciliacionController::class, 'detalle'])->name('admin.conciliacion.detalle');
    Route::post('/admin/conciliacion/item/{id}/aplicar', [ConciliacionController::class, 'aplicarExistente'])->name('admin.conciliacion.aplicar');
    Route::post('/admin/conciliacion/item/{id}/crear',   [ConciliacionController::class, 'crearPago'])->name('admin.conciliacion.crear');

    /* Estados Financieros */
    Route::get('/admin/estados', [EstadosController::class, 'panel'])->name('admin.estados.panel');
    Route::post('/admin/estados/sumas', [EstadosController::class, 'sumas'])->name('admin.estados.sumas');

    // ===== Exports con rate limit configurable =====
    Route::post('/admin/estados/sumas.csv', [EstadosController::class, 'sumasCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.estados.sumas.csv');

    Route::post('/admin/estados/eerr', [EstadosController::class, 'eerr'])->name('admin.estados.eerr');
    Route::post('/admin/estados/eerr.csv', [EstadosController::class, 'eerrCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.estados.eerr.csv');

    Route::post('/admin/estados/balance', [EstadosController::class, 'balance'])->name('admin.estados.balance');
    Route::post('/admin/estados/balance.csv', [EstadosController::class, 'balanceCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.estados.balance.csv');

    Route::post('/admin/estados/comparativo', [EstadosController::class, 'comparativo'])->name('admin.estados.comparativo');
    Route::post('/admin/estados/comparativo.csv', [EstadosController::class, 'comparativoCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.estados.comparativo.csv');

    /* Plan de Cuentas */
    Route::get('/admin/cuentas', [PlanCuentasController::class, 'index'])->name('admin.cuentas.panel');
    Route::post('/admin/cuentas', [PlanCuentasController::class, 'store'])->name('admin.cuentas.store');
    Route::post('/admin/cuentas/{id}/delete', [PlanCuentasController::class, 'destroy'])->name('admin.cuentas.delete');
    Route::get('/admin/cuentas/export.csv', [PlanCuentasController::class, 'exportCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.cuentas.export');
    Route::post('/admin/cuentas/import', [PlanCuentasController::class, 'importCsv'])->name('admin.cuentas.import');

    /* Prorrateo */
    Route::get('/admin/prorrateo', [ProrrateoController::class, 'index'])->name('admin.prorrateo.panel');
    Route::post('/admin/prorrateo', [ProrrateoController::class, 'store'])->name('admin.prorrateo.store');
    Route::post('/admin/prorrateo/{id}/generar', [ProrrateoController::class, 'generar'])->name('admin.prorrateo.generar');

    /* Proveedores / Gastos */
    Route::get('/admin/proveedores', [ProveedorController::class, 'index'])->name('admin.proveedores.panel');
    Route::post('/admin/proveedores', [ProveedorController::class, 'store'])->name('admin.proveedores.store');
    Route::get('/admin/gastos', [GastoController::class, 'index'])->name('admin.gastos.panel');
    Route::post('/admin/gastos', [GastoController::class, 'store'])->name('admin.gastos.store');

    /* Maestros Condominio */
    Route::get('/admin/maestros', [MaestrosCondoController::class, 'panel'])->name('admin.maestros.panel');
    Route::post('/admin/maestros/params', [MaestrosCondoController::class, 'saveParams'])->name('admin.maestros.params.save');
    Route::post('/admin/maestros/interes', [MaestrosCondoController::class, 'saveInteres'])->name('admin.maestros.interes.save');

    /* Estructura */
    Route::get('/admin/condominios', [CondominioController::class, 'index'])
        ->middleware(RoleMiddleware::class . ':super_admin')
        ->name('admin.condos.panel');
    Route::post('/admin/condominios', [CondominioController::class, 'store'])
        ->middleware(RoleMiddleware::class . ':super_admin')
        ->name('admin.condos.store');

    Route::get('/admin/grupos', [GrupoController::class, 'index'])->name('admin.grupos.panel');
    Route::post('/admin/grupos', [GrupoController::class, 'store'])->name('admin.grupos.store');
    Route::get('/admin/unidades', [UnidadController::class, 'index'])->name('admin.unidades.panel');
    Route::post('/admin/unidades', [UnidadController::class, 'store'])->name('admin.unidades.store');

    /* RR.HH. */
    Route::get('/admin/trabajadores', [TrabajadorController::class, 'index'])->name('admin.trab.panel');
    Route::post('/admin/trabajadores', [TrabajadorController::class, 'store'])->name('admin.trab.store');
    Route::post('/admin/contratos', [ContratoController::class, 'store'])->name('admin.contratos.store');

    Route::get('/admin/remuneraciones', [RemuneracionController::class, 'index'])->name('admin.remu.panel');
    Route::post('/admin/remuneraciones', [RemuneracionController::class, 'store'])->name('admin.remu.store');
    Route::post('/admin/remuneraciones/{id}/pagar', [RemuneracionController::class, 'pagar'])->name('admin.remu.pagar');
    Route::post('/admin/remuneraciones/{id}/pagar-ret', [RemuneracionController::class, 'pagarRetenciones'])->name('admin.remu.pagar_ret');

    /* Auditoría y Fondo Reserva */
    Route::get('/admin/auditoria', [AuditoriaController::class, 'index'])->name('admin.audit.panel');

    // ======== FONDO DE RESERVA ========
    Route::get('/admin/fondo-reserva', [FRController::class, 'index'])->name('admin.fr.panel');
    Route::post('/admin/fondo-reserva', [FRController::class, 'store'])->name('admin.fr.store');
    Route::post('/admin/fondo-reserva/export.csv', [FRController::class, 'exportCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.fr.export.csv');

    /* Export CSV */
    Route::get('/admin/export', [ExportController::class, 'panel'])->name('admin.export.panel');
    Route::post('/admin/export/cobros.csv', [ExportController::class, 'cobrosCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.export.cobros.csv');
    Route::post('/admin/export/pagos.csv',  [ExportController::class, 'pagosCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.export.pagos.csv');

    /* Residentes */
    Route::get('/admin/residencias', [ResidenciaController::class, 'index'])->name('admin.residencias.panel');
    Route::post('/admin/residencias', [ResidenciaController::class, 'store'])->name('admin.residencias.store');
    Route::post('/admin/residencias/{id}/terminar', [ResidenciaController::class, 'terminar'])->name('admin.residencias.terminar');

    /* Copropietarios */
    Route::get('/admin/copropietarios', [CopropietarioController::class, 'index'])->name('admin.coprop.panel');
    Route::post('/admin/copropietarios', [CopropietarioController::class, 'store'])->name('admin.coprop.store');
    Route::post('/admin/copropietarios/{id}/terminar', [CopropietarioController::class, 'terminar'])->name('admin.coprop.terminar');

    /* Cargos manuales */
    Route::get('/admin/cargos', [CargoManualController::class, 'index'])->name('admin.cargos.panel');
    Route::post('/admin/cargos/unidad', [CargoManualController::class, 'storeCargoUnidad'])->name('admin.cargos.unidad.store');
    Route::post('/admin/cargos/individual', [CargoManualController::class, 'storeCargoIndividual'])->name('admin.cargos.individual.store');

    /* ========= USUARIOS (coincide con la vista) ========= */
    Route::get('/admin/usuarios', [UserController::class, 'index'])->name('admin.usuarios.panel');
    Route::post('/admin/usuarios', [UserController::class, 'store'])->name('admin.usuarios.store');
    Route::post('/admin/usuarios/{id}/update', [UserController::class, 'update'])->name('admin.usuarios.update');
    Route::post('/admin/usuarios/{id}/toggle', [UserController::class, 'toggle'])->name('admin.usuarios.toggle');
    Route::post('/admin/usuarios/{id}/reset',  [UserController::class, 'resetPassword'])->name('admin.usuarios.reset');
    Route::post('/admin/usuarios/{id}/delete', [UserController::class, 'destroy'])->name('admin.usuarios.delete');

    /* Asignación admin-condo */
    Route::get('/admin/asignacion-admin-condo', [AdminUserCondoController::class, 'index'])
        ->middleware(RoleMiddleware::class . ':super_admin')
        ->name('admin.uac.panel');
    Route::post('/admin/asignacion-admin-condo', [AdminUserCondoController::class, 'save'])
        ->middleware(RoleMiddleware::class . ':super_admin')
        ->name('admin.uac.save');

    /* Reportes */
    Route::get('/admin/reportes', [ReportesController::class, 'panel'])->name('admin.reportes.panel');
    Route::post('/admin/reportes/antiguedad', [ReportesController::class, 'antiguedad'])->name('admin.reportes.antiguedad');
    Route::post('/admin/reportes/antiguedad.csv', [ReportesController::class, 'antiguedadCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.reportes.antiguedad.csv');
    Route::post('/admin/reportes/recaudacion', [ReportesController::class, 'recaudacion'])->name('admin.reportes.recaudacion');
    Route::post('/admin/reportes/recaudacion.csv', [ReportesController::class, 'recaudacionCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.reportes.recaudacion.csv');
    Route::post('/admin/reportes/antiguedad-pivot', [ReportesController::class, 'antiguedadPivot'])->name('admin.reportes.antiguedad.pivot');
    Route::post('/admin/reportes/antiguedad-pivot.csv', [ReportesController::class, 'antiguedadPivotCsv'])
        ->middleware('throttle:' . config('security.rate_limits.exports_per_min', 20) . ',1')
        ->name('admin.reportes.antiguedad.pivot.csv');
});

/* ==========================================================
   Portal (copropietario + residente) – mismo orden
   ========================================================== */
Route::middleware([
    'auth',
    EnsureCondoContext::class,
    CondoAccess::class,
    RoleMiddleware::class . ':copropietario,residente',
    SecurityHeaders::class,
    ForceHttps::class,
])->group(function () {
    Route::get('/mi-cuenta', [PortalResidenteController::class, 'miCuenta'])->name('mi.cuenta');
    Route::get('/estado-cuenta', [PortalResidenteController::class, 'estadoCuenta'])->name('estado.cuenta');

    // START debe ser POST
    Route::post('/pagos/webpay/start', [WebpayController::class, 'start'])
        ->middleware(MaxPostSize::class)
        ->name('webpay.start');
});

/* ====== Webpay return/notify fuera del grupo (acepta POST) ====== */
Route::match(['GET', 'POST'], '/pagos/webpay/return',  [WebpayController::class, 'return'])
    ->middleware([SecurityHeaders::class, ForceHttps::class])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webpay.return');

Route::post('/pagos/webpay/notify', [WebpayController::class, 'notify'])
    ->middleware([SecurityHeaders::class, ForceHttps::class, 'throttle:60,1'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webpay.notify');

/* PDFs */
Route::get('/pagos/recibo/{id}.pdf', [PagoController::class, 'reciboPdf'])
    ->middleware(['auth', SecurityHeaders::class, ForceHttps::class])
    ->name('pagos.recibo.pdf');

Route::get('/cobros/{id}/aviso.pdf', [AvisoCobroController::class, 'pdf'])
    ->middleware(['auth', SecurityHeaders::class, ForceHttps::class])
    ->name('cobro.aviso.pdf');

/* Comprobante firmado */
Route::get('/comprobantes/{id}.pdf', [ComprobanteController::class, 'pdf'])
    ->middleware(['auth', SecurityHeaders::class, ForceHttps::class])
    ->name('comprobante.pdf');

Route::get('/comprobantes/verify', [ComprobanteController::class, 'verify'])
    ->middleware([SecurityHeaders::class, ForceHttps::class])
    ->name('comprobante.verify');
