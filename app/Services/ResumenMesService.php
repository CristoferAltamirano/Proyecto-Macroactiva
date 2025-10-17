<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResumenMesService
{
    public static function generar(string $periodo, int $idCondominio): void
    {
        $totalGastos = (float) DB::table('gasto')->where('id_condominio',$idCondominio)->where('periodo',$periodo)->sum('total');

        $totalCargos = (float) DB::table('cargo_unidad as cu')
            ->join('unidad as u','u.id_unidad','=','cu.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('cu.periodo',$periodo)->sum('cu.monto');

        $totalCargos += (float) DB::table('cargo_individual as ci')
            ->join('unidad as u','u.id_unidad','=','ci.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('ci.periodo',$periodo)->sum('ci.monto');

        $totalInteres = (float) DB::table('cobro_detalle as cd')
            ->join('cobro as c','c.id_cobro','=','cd.id_cobro')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('cd.tipo','interes_mora')->where('cd.glosa',$periodo)->sum('cd.monto');

        $totalDesc = (float) DB::table('cobro_detalle as cd')
            ->join('cobro as c','c.id_cobro','=','cd.id_cobro')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('c.periodo',$periodo)->where('cd.tipo','descuento')->sum('cd.monto');

        $totalPagado = (float) DB::table('pago as p')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('p.periodo',$periodo)->sum('p.monto');

        $saldoPorCobrar = (float) DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('c.periodo',$periodo)->sum('c.saldo');

        DB::table('resumen_mensual')->updateOrInsert(
            ['id_condominio'=>$idCondominio,'periodo'=>$periodo],
            [
                'total_gastos'=>round($totalGastos,2),'total_cargos'=>round($totalCargos,2),
                'total_interes'=>round($totalInteres,2),'total_descuentos'=>round($totalDesc,2),
                'total_pagado'=>round($totalPagado,2),'saldo_por_cobrar'=>round($saldoPorCobrar,2),
                'generado_at'=>now()
            ]
        );
    }
}
