<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function panel(){ return view('admin_export_panel'); }

    public function cobrosCsv(Request $r)
    {
        $d = $r->validate(['periodo'=>['required','regex:/^[0-9]{6}$/'],'id_condominio'=>['nullable','integer']]);
        $rows = \DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->join('condominio as co','co.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_cobro_estado as e','e.id_cobro_estado','=','c.id_cobro_estado')
            ->selectRaw("c.id_cobro, co.nombre condominio, u.codigo unidad, c.periodo, e.codigo estado, c.total_cargos, c.total_interes, c.total_descuentos, c.total_pagado, c.saldo")
            ->where('c.periodo',$d['periodo'])
            ->when($d['id_condominio'] ?? null, fn($q,$id)=>$q->where('co.id_condominio',$id))
            ->orderBy('co.nombre')->orderBy('u.codigo')->get();

        return response()->streamDownload(function() use($rows){
            $out=fopen('php://output','w');
            fputcsv($out,['id_cobro','condominio','unidad','periodo','estado','total_cargos','total_interes','total_descuentos','total_pagado','saldo']);
            foreach($rows as $r) fputcsv($out,[(int)$r->id_cobro,$r->condominio,$r->unidad,$r->periodo,$r->estado,$r->total_cargos,$r->total_interes,$r->total_descuentos,$r->total_pagado,$r->saldo]);
            fclose($out);
        }, "cobros_{$d['periodo']}.csv", ['Content-Type'=>'text/csv']);
    }

    public function pagosCsv(Request $r)
    {
        $d = $r->validate(['periodo'=>['required','regex:/^[0-9]{6}$/'],'id_condominio'=>['nullable','integer']]);
        $rows = \DB::table('pago as p')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->join('condominio as co','co.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as m','m.id_metodo_pago','=','p.id_metodo_pago')
            ->selectRaw("p.id_pago, co.nombre condominio, u.codigo unidad, p.periodo, p.fecha_pago, p.monto, m.codigo metodo, p.ref_externa")
            ->where('p.periodo',$d['periodo'])
            ->when($d['id_condominio'] ?? null, fn($q,$id)=>$q->where('co.id_condominio',$id))
            ->orderBy('co.nombre')->orderBy('u.codigo')->orderBy('p.fecha_pago')->get();

        return response()->streamDownload(function() use($rows){
            $out=fopen('php://output','w');
            fputcsv($out,['id_pago','condominio','unidad','periodo','fecha_pago','monto','metodo','ref_externa']);
            foreach($rows as $r) fputcsv($out,[(int)$r->id_pago,$r->condominio,$r->unidad,$r->periodo,$r->fecha_pago,$r->monto,$r->metodo,$r->ref_externa]);
            fclose($out);
        }, "pagos_{$d['periodo']}.csv", ['Content-Type'=>'text/csv']);
    }
}
