<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ProrrateoService;
use App\Services\AuditoriaService;

class ProrrateoController extends Controller
{
    public function index()
    {
        $reglas = DB::table('prorrateo_regla as r')
            ->join('condominio as c','c.id_condominio','=','r.id_condominio')
            ->join('cat_concepto_cargo as k','k.id_concepto_cargo','=','r.id_concepto_cargo')
            ->select('r.*','c.nombre as condominio','k.nombre as concepto')
            ->orderByDesc('r.vigente_desde')->limit(50)->get();

        $condos = DB::table('condominio')->orderBy('nombre')->get();
        $conceptos = DB::table('cat_concepto_cargo')->orderBy('nombre')->get();
        return view('prorrateo_panel', compact('reglas','condos','conceptos'));
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'id_condominio'=>['required','integer'],
            'id_concepto_cargo'=>['required','integer'],
            'tipo'=>['required','in:ordinario,extra,especial'],
            'criterio'=>['required','in:coef_prop,por_m2,igualitario,por_tipo,monto_fijo'],
            'monto_total'=>['nullable','numeric','min:0'],
            'peso_vivienda'=>['nullable','numeric','min:0'],
            'peso_bodega'=>['nullable','numeric','min:0'],
            'peso_estacionamiento'=>['nullable','numeric','min:0'],
            'vigente_desde'=>['required','date'],
            'vigente_hasta'=>['nullable','date'],
            'descripcion'=>['nullable','string','max:300'],
        ]);
        $id = DB::table('prorrateo_regla')->insertGetId($d);
        ProrrateoService::poblarFactores($id);
        AuditoriaService::log('prorrateo_regla', $id, 'CREAR', $d);

        return back()->with('ok','Regla creada y factores poblados.');
    }

    public function generar(Request $r, int $id)
    {
        $data = $r->validate(['periodo'=>['required','regex:/^[0-9]{6}$/']]);
        $n = ProrrateoService::generarCargos($id, $data['periodo']);
        AuditoriaService::log('prorrateo_regla', $id, 'GENERAR', ['periodo'=>$data['periodo'],'cargos_creados'=>$n]);

        return back()->with('ok',"Generados $n cargos para el periodo {$data['periodo']}.");
    }
}
