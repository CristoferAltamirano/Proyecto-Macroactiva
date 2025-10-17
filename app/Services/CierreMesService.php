<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CierreMesService
{
    public static function datosCierre(int $idCondominio, string $periodo): array
    {
        $condo = DB::table('condominio as c')
            ->leftJoin('cat_tipo_cuenta as t','t.id_tipo_cuenta','=','c.id_tipo_cuenta')
            ->select('c.*','t.codigo as tipo_cuenta')
            ->where('c.id_condominio',$idCondominio)->first();

        // GASTOS (detalle por categoría)
        $gastos = DB::table('gasto as g')
            ->leftJoin('gasto_categoria as gc','gc.id_gasto_categ','=','g.id_gasto_categ')
            ->where('g.id_condominio',$idCondominio)
            ->where('g.periodo',$periodo)
            ->select('gc.nombre as categoria','g.total','g.descripcion','g.documento_folio','g.fecha_emision')
            ->orderBy('g.id_gasto')->get();
        $gastosPorCat = DB::table('gasto as g')
            ->leftJoin('gasto_categoria as gc','gc.id_gasto_categ','=','g.id_gasto_categ')
            ->where('g.id_condominio',$idCondominio)->where('g.periodo',$periodo)
            ->groupBy('gc.nombre')
            ->selectRaw('COALESCE(gc.nombre,"(Sin categoría)") as categoria, SUM(g.total) as total')
            ->orderBy('categoria')->get();
        $totalGastos = (float) ($gastosPorCat->sum('total') ?? 0);

        // COBROS (del periodo)
        $cobrosAgg = DB::table('cobro')
            ->where('periodo',$periodo)
            ->whereIn('id_unidad', function($q) use($idCondominio){
                $q->from('unidad as u')->join('grupo as g','g.id_grupo','=','u.id_grupo')
                  ->where('g.id_condominio',$idCondominio)->select('u.id_unidad');
            })
            ->selectRaw('SUM(total_cargos) total_cargos, SUM(total_interes) total_interes, SUM(total_descuentos) total_descuentos, SUM(total_pagado) total_pagado, SUM(saldo) saldo')
            ->first();

        // PAGOS del periodo (por método)
        $pagosPorMetodo = DB::table('pago as p')
            ->leftJoin('cat_metodo_pago as m','m.id_metodo_pago','=','p.id_metodo_pago')
            ->where('p.periodo',$periodo)
            ->whereIn('p.id_unidad', function($q) use($idCondominio){
                $q->from('unidad as u')->join('grupo as g','g.id_grupo','=','u.id_grupo')
                  ->where('g.id_condominio',$idCondominio)->select('u.id_unidad');
            })
            ->groupBy('m.nombre')
            ->selectRaw('COALESCE(m.nombre,"Otro") as metodo, SUM(p.monto) as total')
            ->orderBy('metodo')->get();
        $totalPagos = (float) ($pagosPorMetodo->sum('total') ?? 0);

        // FONDO DE RESERVA del periodo
        $frMov = DB::table('fondo_reserva_mov')
            ->where('id_condominio',$idCondominio)
            ->where('periodo',$periodo)->get();
        $frAbonos = (float) $frMov->where('tipo','abono')->sum('monto');
        $frCargos = (float) $frMov->where('tipo','cargo')->sum('monto');
        $frNeto   = $frAbonos - $frCargos;

        // Resumen_mensual guardado (si existe)
        $resumen = DB::table('resumen_mensual')
            ->where(['id_condominio'=>$idCondominio,'periodo'=>$periodo])->first();

        return [
            'condo' => $condo,
            'periodo' => $periodo,
            'gastos_detalle' => $gastos,
            'gastos_por_cat' => $gastosPorCat,
            'total_gastos'   => $totalGastos,
            'cobros' => [
                'cargos'     => (float)($cobrosAgg->total_cargos ?? 0),
                'interes'    => (float)($cobrosAgg->total_interes ?? 0),
                'descuentos' => (float)($cobrosAgg->total_descuentos ?? 0),
                'pagado'     => (float)($cobrosAgg->total_pagado ?? 0),
                'saldo'      => (float)($cobrosAgg->saldo ?? 0),
            ],
            'pagos_por_metodo'=> $pagosPorMetodo,
            'total_pagos'     => $totalPagos,
            'fondo_reserva'   => [
                'abonos'=>$frAbonos, 'cargos'=>$frCargos, 'neto'=>$frNeto
            ],
            'resumen_guardado'=> $resumen,
            'generado_at'     => now()->format('Y-m-d H:i'),
        ];
    }
}
