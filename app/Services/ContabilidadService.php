<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Cobro;
use App\Models\Pago;
use App\Traits\ManejaCuentasContables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContabilidadService
{
    use ManejaCuentasContables;

    /**
     * Registra el asiento contable para la generación masiva de cobros.
     */
    public function registrarGeneracionCobros($cobros, int $idCondominio)
    {
        $montoTotal = $cobros->sum('monto_total');
        $periodo = $cobros->first()->periodo->translatedFormat('F Y');

        $idCuentasPorCobrar = $this->obtenerIdCuenta('cuentas_por_cobrar');
        $idIngresosGastoComun = $this->obtenerIdCuenta('ingresos_gasto_comun');

        if (!$idCuentasPorCobrar || !$idIngresosGastoComun) {
            Log::error("Contabilidad no registrada: Faltan cuentas clave (Cuentas por Cobrar o Ingresos).");
            return;
        }

        if ($montoTotal > 0) {
            $this->registrarAsiento(
                now()->toDateString(), $idCondominio, $idCuentasPorCobrar, $montoTotal, 0,
                "Generación de cobros para el periodo {$periodo}", 'cobros_lote', $cobros->first()->periodo->format('Ym')
            );
            $this->registrarAsiento(
                now()->toDateString(), $idCondominio, $idIngresosGastoComun, 0, $montoTotal,
                "Generación de cobros para el periodo {$periodo}", 'cobros_lote', $cobros->first()->periodo->format('Ym')
            );
        }
    }

    /**
     * Registra el asiento contable para un nuevo gasto.
     */
    public function registrarGasto(Gasto $gasto)
    {
        $idCondominio = optional(optional($gasto->unidad)->grupo)->id_condominio;
        if (!$idCondominio) {
            Log::warning("No se pudo registrar asiento para el gasto {$gasto->id}: no se encontró condominio asociado.");
            return;
        }

        $idCuentaEgresos = $this->obtenerIdCuenta('egresos_general');
        $idCuentasPorPagar = $this->obtenerIdCuenta('cuentas_por_pagar');

        if (!$idCuentaEgresos || !$idCuentasPorPagar) {
            Log::error("Contabilidad de gasto no registrada: Faltan cuentas clave (Egresos o Cuentas por Pagar).");
            return;
        }

        $this->registrarAsiento(
            $gasto->fecha_gasto, $idCondominio, $idCuentaEgresos, $gasto->monto, 0,
            "Registro de gasto: {$gasto->descripcion}", 'gastos', $gasto->id
        );
        $this->registrarAsiento(
            $gasto->fecha_gasto, $idCondominio, $idCuentasPorPagar, 0, $gasto->monto,
            "Registro de gasto: {$gasto->descripcion}", 'gastos', $gasto->id
        );
    }

    /**
     * Registra el asiento contable para un pago recibido de un residente.
     */
    public function registrarPago(Pago $pago)
    {
        $idCondominio = optional(optional($pago->unidad)->grupo)->id_condominio;
        if (!$idCondominio) {
            Log::warning("No se pudo registrar asiento para el pago {$pago->id}: no se encontró condominio asociado.");
            return;
        }

        $idCuentaCajaBanco = $this->obtenerIdCuenta('caja_banco');
        $idCuentasPorCobrar = $this->obtenerIdCuenta('cuentas_por_cobrar');

        if (!$idCuentaCajaBanco || !$idCuentasPorCobrar) {
            Log::error("Contabilidad de pago no registrada: Faltan cuentas clave (Caja/Banco o Cuentas por Cobrar).");
            return;
        }

        $this->registrarAsiento(
            $pago->fecha_pago, $idCondominio, $idCuentaCajaBanco, $pago->monto, 0,
            "Pago recibido de unidad {$pago->unidad->numero}", 'pagos', $pago->id
        );
        $this->registrarAsiento(
            $pago->fecha_pago, $idCondominio, $idCuentasPorCobrar, 0, $pago->monto,
            "Pago recibido de unidad {$pago->unidad->numero}", 'pagos', $pago->id
        );
    }

    /**
     * Método privado para insertar un movimiento en el libro diario.
     */
    private function registrarAsiento(string $fecha, int $idCondominio, int $idCtaContable, float $debe, float $haber, string $glosa, $refTabla = null, $refId = null): void
    {
        try {
            DB::table('libro_movimiento')->insert([
                'fecha' => $fecha,
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idCtaContable,
                'debe' => $debe,
                'haber' => $haber,
                'glosa' => $glosa,
                'ref_tabla' => $refTabla,
                'ref_id' => $refId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error al registrar asiento contable: " . $e->getMessage(), [
                'glosa' => $glosa,
                'debe' => $debe,
                'haber' => $haber,
            ]);
        }
    }
}