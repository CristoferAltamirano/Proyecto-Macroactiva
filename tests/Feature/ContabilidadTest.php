<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Unidad;
use App\Services\ContabilidadService;
use App\Models\Condominio;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos las cuentas contables necesarias para las pruebas.
        DB::table('cuenta_contable')->insert([
            ['codigo' => '1.1.01', 'nombre' => 'Caja/Banco'],
            ['codigo' => '1.1.02', 'nombre' => 'Cuentas por Cobrar'],
            ['codigo' => '2.1.01', 'nombre' => 'Cuentas por Pagar'],
            ['codigo' => '4.1.01', 'nombre' => 'Ingresos Gasto Común'],
            ['codigo' => '5.1.01', 'nombre' => 'Egresos General'],
        ]);
    }

    public function test_contabilidad_service_registers_generacion_cobros()
    {
        $condominio = Condominio::factory()->create();
        $grupo = Grupo::factory()->create(['condominio_id' => $condominio->id]);
        $unidad = Unidad::factory()->create(['id_grupo' => $grupo->id]);
        $cobros = Cobro::factory()->count(3)->create(['unidad_id' => $unidad->id, 'monto_total' => 10000]);
        $idCondominio = $unidad->grupo->id_condominio;

        (new ContabilidadService())->registrarGeneracionCobros($cobros, $idCondominio);

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
        $gasto = Gasto::factory()->create(['monto' => 5000]);

        (new ContabilidadService())->registrarGasto($gasto);

        $idEgresos = DB::table('cuenta_contable')->where('codigo', '5.1.01')->value('id_cta_contable');
        $idCuentasPorPagar = DB::table('cuenta_contable')->where('codigo', '2.1.01')->value('id_cta_contable');

        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idEgresos,
            'debe' => 5000,
        ]);
        $this->assertDatabaseHas('libro_movimiento', [
            'id_cta_contable' => $idCuentasPorPagar,
            'haber' => 5000,
        ]);
    }

    public function test_contabilidad_service_registers_pago()
    {
        $pago = Pago::factory()->create(['monto' => 12000]);

        (new ContabilidadService())->registrarPago($pago);

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