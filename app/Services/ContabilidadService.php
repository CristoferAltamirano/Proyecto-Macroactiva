<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContabilidadService
{
    public function registrarGeneracionCobros($cobros, ?int $idCondominio = null): void
    {
        if ($cobros->isEmpty()) {
            return;
        }

        if ($idCondominio === null) {
            $first = $cobros->first();
            if (isset($first->id_condominio)) {
                $idCondominio = (int)$first->id_condominio;
            } elseif (auth()->check() && isset(auth()->user()->id_condominio)) {
                $idCondominio = (int)auth()->user()->id_condominio;
            } else {
                $idCondominio = 1; // Fallback to a default
            }
        }

        $montoTotal = $cobros->sum('monto_total');
        $periodo = $cobros->first()->periodo;

        $idCuentasPorCobrar = DB::table('cuenta_contable')->where('codigo', '1.1.02')->value('id_cta_contable');
        $idIngresosComunes = DB::table('cuenta_contable')->where('codigo', '4.1.01')->value('id_cta_contable');

        DB::table('libro_movimiento')->insert([
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idCuentasPorCobrar,
                'glosa' => "Provisión gastos comunes {$periodo}",
                'debe' => $montoTotal,
                'haber' => 0,
                'fecha' => now(),
            ],
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idIngresosComunes,
                'glosa' => "Provisión gastos comunes {$periodo}",
                'debe' => 0,
                'haber' => $montoTotal,
                'fecha' => now(),
            ],
        ]);
    }

    public function registrarGasto($gasto, int $idCondominio): void
    {
        $idEgresos = DB::table('cuenta_contable')->where('codigo', '5.1.01')->value('id_cta_contable');
        $idCuentasPorPagar = DB::table('cuenta_contable')->where('codigo', '2.1.01')->value('id_cta_contable');

        DB::table('libro_movimiento')->insert([
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idEgresos,
                'glosa' => $gasto->descripcion,
                'debe' => $gasto->total,
                'haber' => 0,
                'fecha' => $gasto->fecha_emision,
            ],
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idCuentasPorPagar,
                'glosa' => "Gasto por " . $gasto->descripcion,
                'debe' => 0,
                'haber' => $gasto->total,
                'fecha' => $gasto->fecha_emision,
            ],
        ]);
    }

    public function registrarPago($pago, int $idCondominio): void
    {
        $idCajaBanco = DB::table('cuenta_contable')->where('codigo', '1.1.01')->value('id_cta_contable');
        $idCuentasPorCobrar = DB::table('cuenta_contable')->where('codigo', '1.1.02')->value('id_cta_contable');

        DB::table('libro_movimiento')->insert([
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idCajaBanco,
                'glosa' => 'Pago de residente',
                'debe' => $pago->monto_pagado,
                'haber' => 0,
                'fecha' => $pago->fecha_pago,
            ],
            [
                'id_condominio' => $idCondominio,
                'id_cta_contable' => $idCuentasPorCobrar,
                'glosa' => 'Pago de residente',
                'debe' => 0,
                'haber' => $pago->monto_pagado,
                'fecha' => $pago->fecha_pago,
            ],
        ]);
    }
}