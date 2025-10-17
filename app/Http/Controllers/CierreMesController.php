<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CierreMesService;

class CierreMesController extends Controller
{
    /* ================== Helpers ================== */

    /** Devuelve el id_condominio desde el request o, si no viene, desde la sesión (selector superior). */
    private function condo(Request $r): int
    {
        $cid = $r->input('id_condominio') ?? session('ctx_condo_id');
        if (!$cid) abort(422, 'Debe seleccionar un condominio.');
        return (int) $cid;
    }

    /** Valida un período AAAAMM y lo retorna como string. */
    private function periodo(Request $r, string $field = 'periodo'): string
    {
        $p = (string) $r->input($field);
        if (!preg_match('/^\d{6}$/', $p)) {
            abort(422, 'Periodo inválido. Use AAAAMM.');
        }
        return $p;
    }

    /* ================== Panel ================== */

    public function panel()
    {
        $condos = DB::table('condominio')->orderBy('nombre')->get();
        $ctx = session('ctx_condo_id');

        $ultimos = DB::table('resumen_mensual')
            ->join('condominio as c','c.id_condominio','=','resumen_mensual.id_condominio')
            ->select('resumen_mensual.*','c.nombre as condominio')
            ->when($ctx, fn($q)=>$q->where('resumen_mensual.id_condominio', $ctx))
            ->orderByDesc('generado_at')
            ->limit(30)
            ->get();

        return view('admin_cierres', [
            'condos'  => $condos,
            'ultimos' => $ultimos,
            'ctx'     => $ctx,
            'hoy'     => now()->format('Ym'),
        ]);
    }

    /* ================== Acciones ================== */

    public function cerrar(Request $r)
    {
        $idCondo = $this->condo($r);
        $periodo = $this->periodo($r);

        $data = CierreMesService::datosCierre($idCondo, $periodo);

        // Guardar o actualizar resumen mensual
        DB::table('resumen_mensual')->updateOrInsert(
            ['id_condominio'=>$idCondo,'periodo'=>$periodo],
            [
                'total_gastos'     => $data['total_gastos'],
                'total_cargos'     => $data['cobros']['cargos'],
                'total_interes'    => $data['cobros']['interes'],
                'total_descuentos' => $data['cobros']['descuentos'],
                'total_pagado'     => $data['cobros']['pagado'],
                'saldo_por_cobrar' => $data['cobros']['saldo'],
                'generado_at'      => now(),
            ]
        );

        // Registrar/actualizar marca de cierre
        DB::table('periodo_cierre')->updateOrInsert(
            ['id_condominio'=>$idCondo,'periodo'=>$periodo],
            ['cerrado_por'=>auth()->id(),'cerrado_at'=>now()]
        );

        return back()->with('ok','Periodo '.$periodo.' cerrado / actualizado.');
    }

    public function reabrir(Request $r)
    {
        $idCondo = $this->condo($r);
        $periodo = $this->periodo($r);

        // Eliminar resumen mensual
        DB::table('resumen_mensual')
            ->where('id_condominio',$idCondo)
            ->where('periodo',$periodo)
            ->delete();

        // Eliminar marca de cierre
        DB::table('periodo_cierre')
            ->where('id_condominio',$idCondo)
            ->where('periodo',$periodo)
            ->delete();

        return back()->with('ok','Periodo '.$periodo.' reabierto (resumen eliminado).');
    }

    /** Estado rápido en JSON (útil para depurar) */
    public function status(Request $r)
    {
        $id = (int)($r->query('id_condominio') ?? session('ctx_condo_id') ?? 0);

        $rows = DB::table('resumen_mensual')
            ->when($id>0, fn($q)=>$q->where('id_condominio',$id))
            ->orderByDesc('periodo')
            ->limit(24)
            ->get();

        return response()->json($rows);
    }

    /** Comparación simple entre resumen guardado y cálculo en vivo */
    public function diff(Request $r)
    {
        $idCondo = $this->condo($r);
        $periodo = $this->periodo($r);

        $live  = CierreMesService::datosCierre($idCondo,$periodo);
        $saved = DB::table('resumen_mensual')
            ->where('id_condominio',$idCondo)
            ->where('periodo',$periodo)
            ->first();

        $cmp = $saved ? [
            'gastos'  => round($live['total_gastos'] - (float)$saved->total_gastos,2),
            'cargos'  => round($live['cobros']['cargos'] - (float)$saved->total_cargos,2),
            'interes' => round($live['cobros']['interes'] - (float)$saved->total_interes,2),
            'desc'    => round($live['cobros']['descuentos'] - (float)$saved->total_descuentos,2),
            'pagado'  => round($live['cobros']['pagado'] - (float)$saved->total_pagado,2),
            'saldo'   => round($live['cobros']['saldo'] - (float)$saved->saldo_por_cobrar,2),
        ] : null;

        return response()->json(['diff'=>$cmp,'saved'=>$saved,'live'=>$live]);
    }

    /** PDF formal (si DomPDF está instalado); si no, HTML imprimible */
    public function pdf(Request $r)
    {
        $idCondo = $this->condo($r);
        $periodo = $this->periodo($r);

        $data = CierreMesService::datosCierre($idCondo,$periodo);
        $html = view('cierre_pdf', $data + [
            'id_condominio' => $idCondo,
            'periodo'       => $periodo,
        ])->render();

        // DomPDF (barryvdh/laravel-dompdf)
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('letter');
            return $pdf->download("cierre_{$idCondo}_{$periodo}.pdf");
        }

        // Fallback HTML
        return response($html, 200)->header('Content-Type','text/html');
    }
}
