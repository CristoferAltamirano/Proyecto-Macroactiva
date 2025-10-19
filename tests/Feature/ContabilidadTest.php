<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Unidad;
use App\Services\ContabilidadService;
use App\Models\Condominio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\CuentaContable;
use App\Models\Grupo;

class ContabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos las cuentas contables necesarias para las pruebas.
        CuentaContable::factory()->create(['codigo' => '1.1.01', 'nombre' => 'Caja/Banco']);
        CuentaContable::factory()->create(['codigo' => '1.1.02', 'nombre' => 'Cuentas por Cobrar']);
        CuentaContable::factory()->create(['codigo' => '2.1.01', 'nombre' => 'Cuentas por Pagar']);
        CuentaContable::factory()->create(['codigo' => '4.1.01', 'nombre' => 'Ingresos Gasto Común']);
        CuentaContable::factory()->create(['codigo' => '5.1.01', 'nombre' => 'Egresos General']);
    }

    public function test_contabilidad_service_registers_generacion_cobros()
    {
        $condominio = Condominio::factory()->create();
        $grupo = Grupo::factory()->create(['id_condominio' => $condominio->id_condominio]);
        $unidad = Unidad::factory()->create(['id_grupo' => $grupo->id_grupo]);
        $cobros = Cobro::factory()->count(3)->create(['id_unidad' => $unidad->id_unidad, 'monto_total' => 10000]);

        (new ContabilidadService())->registrarGeneracionCobros($cobros, $condominio->id_condominio);

        $idCuentasPorCobrar = DB::table('cuenta_contable')->where('codigo', '1.1.02')->value('id_cta_contable');
        $idIngresos = DB::table('cuenta_contable')->where('codigo', '4.1.01')->value('id_cta_contable');

        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idCuentasPorCobrar,
            'debe' => 30000,
        ]);
        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idIngresos,
            'haber' => 30000,
        ]);
    }

    public function test_contabilidad_service_registers_gasto()
    {
        $condominio = Condominio::factory()->create();
        $gasto = Gasto::factory()->create(['neto' => 5000, 'iva' => 950, 'condominio_id' => $condominio->id_condominio]);

        (new ContabilidadService())->registrarGasto($gasto, $condominio->id_condominio);

        $idEgresos = DB::table('cuenta_contable')->where('codigo', '5.1.01')->value('id_cta_contable');
        $idCuentasPorPagar = DB::table('cuenta_contable')->where('codigo', '2.1.01')->value('id_cta_contable');

        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idEgresos,
            'debe' => 5950,
        ]);
        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idCuentasPorPagar,
            'haber' => 5950,
        ]);
    }

    public function test_contabilidad_service_registers_pago()
    {
        $condominio = Condominio::factory()->create();
        $grupo = Grupo::factory()->create(['id_condominio' => $condominio->id_condominio]);
        $unidad = Unidad::factory()->create(['id_grupo' => $grupo->id_grupo]);
        $cobro = Cobro::factory()->create(['id_unidad' => $unidad->id_unidad]);
        $pago = Pago::factory()->create(['monto_pagado' => 12000, 'id_unidad' => $unidad->id_unidad, 'cobro_id' => $cobro->id_cobro]);

        (new ContabilidadService())->registrarPago($pago, $condominio->id_condominio);

        $idCajaBanco = DB::table('cuenta_contable')->where('codigo', '1.1.01')->value('id_cta_contable');
        $idCuentasPorCobrar = DB::table('cuenta_contable')->where('codigo', '1.1.02')->value('id_cta_contable');

        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idCajaBanco,
            'debe' => 12000,
        ]);
        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idCuentasPorCobrar,
            'haber' => 12000,
        ]);
    }
}