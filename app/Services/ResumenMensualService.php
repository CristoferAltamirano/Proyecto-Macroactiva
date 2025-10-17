<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResumenMensualService
{
    /** Calcula y upserta resumen_mensual para condominio/periodo */
    public static function generar(int $idCondominio, string $periodo): int
    {
        // Totales de gastos del periodo
        $totalGastos = (float) DB::table('gasto')
            ->where('id_condominio',$idCondominio)->where('periodo',$periodo)
            ->sum('total');

        // Cargos, descuentos e intereses de cobros del periodo
        $c = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('c.periodo',$periodo)
            ->selectRaw('SUM(c.total_cargos) tc, SUM(c.total_descuentos) td, SUM(c.total_interes) ti, SUM(c.total_pagado) tp, SUM(c.saldo) s')
            ->first();

        DB::table('resumen_mensual')->updateOrInsert(
            ['id_condominio'=>$idCondominio, 'periodo'=>$periodo],
            [
                'total_gastos'     => (float)($totalGastos),
                'total_cargos'     => (float)($c->tc ?? 0),
                'total_interes'    => (float)($c->ti ?? 0),
                'total_descuentos' => (float)($c->td ?? 0),
                'total_pagado'     => (float)($c->tp ?? 0),
                'saldo_por_cobrar' => (float)($c->s ?? 0),
                'generado_at'      => now(),
            ]
        );
        return 1;
    }

    public static function eliminar(int $idCondominio, string $periodo): int
    {
        return DB::table('resumen_mensual')->where('id_condominio',$idCondominio)->where('periodo',$periodo)->delete();
    }
}
