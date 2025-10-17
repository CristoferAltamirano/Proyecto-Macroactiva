<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CierreAnualController extends Controller
{
    /**
     * Cierra el año contable:
     * - Toma saldos 4xxx (ingresos) y 5xxx (gastos) del 1/1 al 31/12.
     * - Genera asientos de cierre contra 3201 “Resultados acumulados”.
     * - Registra en cierre_anual.
     */
    public function cerrar(Request $r)
    {
        $data = $r->validate([
            'anio' => ['required','integer','min:2000','max:2100'],
        ]);
        $anio = (int)$data['anio'];
        $cid  = session('ctx_condo_id') ?? DB::table('condominio')->value('id_condominio');

        if (!$cid) return back()->with('error','No hay condominio seleccionado.');

        // Si ya está cerrado, aborta
        $ya = DB::table('cierre_anual')->where('id_condominio',$cid)->where('anio',$anio)->first();
        if ($ya) return back()->with('error',"El año $anio ya está cerrado.");

        $desde = "$anio-01-01";
        $hasta = "$anio-12-31";

        // Cuenta 3201
        $cta_3201 = DB::table('cuenta_contable')->where('codigo','3201')->first();
        if (!$cta_3201) return back()->with('error','No existe la cuenta contable 3201 (Resultados acumulados). Créala en el plan de cuentas.');

        // Trae totales por cuenta 4xxx y 5xxx
        $mov = DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc','cc.id_cta_contable','=','l.id_cta_contable')
            ->selectRaw('cc.id_cta_contable, cc.codigo, cc.nombre, ROUND(SUM(l.debe),2) as debe, ROUND(SUM(l.haber),2) as haber')
            ->where('l.id_condominio',$cid)
            ->whereBetween('l.fecha', [$desde, $hasta])
            ->where(function($q){
                $q->where('cc.codigo','like','4%')->orWhere('cc.codigo','like','5%');
            })
            ->groupBy('cc.id_cta_contable','cc.codigo','cc.nombre')
            ->orderBy('cc.codigo')
            ->get();

        if ($mov->isEmpty()) return back()->with('warning',"No hay movimientos 4xxx/5xxx en $anio.");

        DB::beginTransaction();
        try {
            // Registrar cabecera del cierre
            $idCierre = DB::table('cierre_anual')->insertGetId([
                'id_condominio' => $cid,
                'anio'          => $anio,
                'cerrado_por'   => auth()->id(),
                'cerrado_at'    => now(),
            ]);

            // Asientos de cierre: por cada cuenta de ingresos/gastos
            foreach ($mov as $m) {
                $codigo = $m->codigo;
                $montoIngreso = 0.0;
                $montoGasto   = 0.0;

                if (str_starts_with($codigo,'4')) {
                    // Ingresos: saldo = haber - debe
                    $saldo = round($m->haber - $m->debe, 2);
                    if (abs($saldo) >= 0.01) {
                        // Cerrar ingreso: Debitar 4xxx, Acreditar 3201
                        DB::table('libro_movimiento')->insert([
                            'id_condominio' => $cid,
                            'fecha'         => "$anio-12-31",
                            'id_cta_contable'=> $m->id_cta_contable,
                            'debe'          => $saldo,
                            'haber'         => 0,
                            'ref_tabla'     => 'cierre_anual',
                            'ref_id'        => $idCierre,
                            'glosa'         => "Cierre $anio - Cta $codigo",
                        ]);
                        DB::table('libro_movimiento')->insert([
                            'id_condominio' => $cid,
                            'fecha'         => "$anio-12-31",
                            'id_cta_contable'=> $cta_3201->id_cta_contable,
                            'debe'          => 0,
                            'haber'         => $saldo,
                            'ref_tabla'     => 'cierre_anual',
                            'ref_id'        => $idCierre,
                            'glosa'         => "Cierre $anio - Traspaso ingresos $codigo",
                        ]);
                        $montoIngreso += $saldo;
                    }
                } elseif (str_starts_with($codigo,'5')) {
                    // Gastos: saldo = debe - haber
                    $saldo = round($m->debe - $m->haber, 2);
                    if (abs($saldo) >= 0.01) {
                        // Cerrar gasto: Acreditar 5xxx, Debitar 3201
                        DB::table('libro_movimiento')->insert([
                            'id_condominio' => $cid,
                            'fecha'         => "$anio-12-31",
                            'id_cta_contable'=> $m->id_cta_contable,
                            'debe'          => 0,
                            'haber'         => $saldo,
                            'ref_tabla'     => 'cierre_anual',
                            'ref_id'        => $idCierre,
                            'glosa'         => "Cierre $anio - Cta $codigo",
                        ]);
                        DB::table('libro_movimiento')->insert([
                            'id_condominio' => $cid,
                            'fecha'         => "$anio-12-31",
                            'id_cta_contable'=> $cta_3201->id_cta_contable,
                            'debe'          => $saldo,
                            'haber'         => 0,
                            'ref_tabla'     => 'cierre_anual',
                            'ref_id'        => $idCierre,
                            'glosa'         => "Cierre $anio - Traspaso gastos $codigo",
                        ]);
                        $montoGasto += $saldo;
                    }
                }
            }

            DB::commit();
            return back()->with('ok',"Cierre anual $anio registrado.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error','Error en cierre anual: '.$e->getMessage());
        }
    }

    /**
     * Reabre el año contable:
     * - Elimina asientos vinculados (ref_tabla=cierre_anual, ref_id).
     * - Borra registro de cierre_anual.
     */
    public function reabrir(Request $r)
    {
        $data = $r->validate([
            'anio' => ['required','integer','min:2000','max:2100'],
        ]);
        $anio = (int)$data['anio'];
        $cid  = session('ctx_condo_id') ?? DB::table('condominio')->value('id_condominio');

        $cierre = DB::table('cierre_anual')->where('id_condominio',$cid)->where('anio',$anio)->first();
        if (!$cierre) return back()->with('warning',"El año $anio no estaba cerrado.");

        DB::beginTransaction();
        try {
            DB::table('libro_movimiento')
                ->where('ref_tabla','cierre_anual')
                ->where('ref_id',$cierre->id_cierre_anual)
                ->delete();

            DB::table('cierre_anual')->where('id_cierre_anual',$cierre->id_cierre_anual)->delete();

            DB::commit();
            return back()->with('ok',"Cierre anual $anio reabierto.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error','Error al reabrir: '.$e->getMessage());
        }
    }
}
