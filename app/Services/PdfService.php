<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public static function avisoCobro(int $idCobro)
    {
        $c = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('cat_cobro_estado as e','e.id_cobro_estado','=','c.id_cobro_estado')
            ->where('c.id_cobro',$idCobro)
            ->select('c.*','u.codigo as unidad','g.nombre as grupo','e.codigo as estado','g.id_condominio')
            ->first();
        if(!$c) abort(404,'Cobro no encontrado');

        $det = DB::table('cobro_detalle')->where('id_cobro',$idCobro)->get();
        $pagos = DB::table('pago_aplicacion as pa')->join('pago as p','p.id_pago','=','pa.id_pago')
            ->where('pa.id_cobro',$idCobro)->select('p.fecha_pago','pa.monto_aplicado')->get();

        $html = view('pdf.aviso_cobro', compact('c','det','pagos'))->render();
        return Pdf::loadHTML($html)->setPaper('letter');
    }

    public static function cierreMensual(string $periodo, int $idCondominio)
    {
        $condo = DB::table('condominio')->where('id_condominio',$idCondominio)->first() ?? abort(404,'Condominio no encontrado');
        $res = DB::table('resumen_mensual')->where('id_condominio',$idCondominio)->where('periodo',$periodo)->first();
        if (!$res) { \App\Services\ResumenMesService::generar($periodo,$idCondominio);
                     $res = DB::table('resumen_mensual')->where('id_condominio',$idCondominio)->where('periodo',$periodo)->first(); }

        $deudores = DB::table('cobro as c')->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)->where('c.periodo',$periodo)->where('c.saldo','>',0)
            ->select('u.codigo as unidad','c.saldo')->orderByDesc('c.saldo')->limit(20)->get();

        $fr = DB::table('fondo_reserva_mov')->where('id_condominio',$idCondominio)->where('periodo',$periodo)->orderBy('fecha')->get();

        $html = view('pdf.cierre_mensual', compact('condo','periodo','res','deudores','fr'))->render();
        return Pdf::loadHTML($html)->setPaper('letter');
    }
}
