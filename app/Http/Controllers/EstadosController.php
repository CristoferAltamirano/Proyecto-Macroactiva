<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadosController extends Controller
{
    /* ===== Panel ===== */
    public function panel()
    {
        $cid = session('ctx_condo_id');

        return view('admin_estados', [
            'condos' => DB::table('condominio')->orderBy('nombre')->get(),
            'ctx' => $cid,
            'defaults' => [
                'desde'  => now()->startOfMonth()->toDateString(),
                'hasta'  => now()->toDateString(),
                'corte'  => now()->toDateString(),
                'desde2' => now()->copy()->subMonth()->startOfMonth()->toDateString(),
                'hasta2' => now()->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'resultado' => null,
        ]);
    }

    /* ======================= SUMAS Y SALDOS ======================= */
    public function sumas(Request $r)
    {
        $d = $r->validate([
            'desde'         => ['required','date'],
            'hasta'         => ['required','date','after_or_equal:desde'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid  = $d['id_condominio'] ?? session('ctx_condo_id');
        $rows = $this->sumasBuild($d['desde'], $d['hasta'], $cid);

        return view('admin_estados', [
            'condos'   => DB::table('condominio')->orderBy('nombre')->get(),
            'ctx'      => $cid,
            'defaults' => [
                'desde' => $d['desde'],
                'hasta' => $d['hasta'],
                'corte' => now()->toDateString(),
            ],
            'resultado' => [
                'tipo'          => 'sumas',
                'desde'         => $d['desde'],
                'hasta'         => $d['hasta'],
                'rows'          => $rows,
                'id_condominio' => $cid,
            ],
        ]);
    }

    public function sumasCsv(Request $r)
    {
        $d = $r->validate([
            'desde'         => ['required','date'],
            'hasta'         => ['required','date','after_or_equal:desde'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid  = $d['id_condominio'] ?? session('ctx_condo_id');
        $rows = $this->sumasBuild($d['desde'], $d['hasta'], $cid);

        $lines = [['Cuenta','Nombre','Debe','Haber','Saldo']];
        foreach ($rows as $x) {
            $lines[] = [
                $x->codigo,
                $x->nombre,
                number_format($x->debe, 2, '.', ''),
                number_format($x->haber, 2, '.', ''),
                number_format($x->saldo, 2, '.', ''),
            ];
        }

        return response($this->toCsv($lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sumas_saldos_'.$d['desde'].'_'.$d['hasta'].'.csv"',
        ]);
    }

    private function sumasBuild(string $desde, string $hasta, ?int $cid)
    {
        $d1 = $desde.' 00:00:00';
        $d2 = $hasta.' 23:59:59';

        return DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
            ->selectRaw('
                cc.codigo,
                cc.nombre,
                ROUND(SUM(l.debe),2)  as debe,
                ROUND(SUM(l.haber),2) as haber,
                ROUND(SUM(l.debe - l.haber),2) as saldo
            ')
            ->whereBetween('l.fecha', [$d1, $d2])
            ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
            ->groupBy('cc.codigo', 'cc.nombre')
            ->orderBy('cc.codigo')
            ->get();
    }

    /* ======================= ESTADO DE RESULTADOS ======================= */
    public function eerr(Request $r)
    {
        $d = $r->validate([
            'desde'         => ['required','date'],
            'hasta'         => ['required','date','after_or_equal:desde'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid = $d['id_condominio'] ?? session('ctx_condo_id');

        $d1 = $d['desde'].' 00:00:00';
        $d2 = $d['hasta'].' 23:59:59';

        $agg = DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
            ->selectRaw('
                cc.codigo,
                cc.nombre,
                ROUND(SUM(l.debe),2)  as debe,
                ROUND(SUM(l.haber),2) as haber
            ')
            ->whereBetween('l.fecha', [$d1, $d2])
            ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
            ->where(function($q){
                $q->where('cc.codigo', 'like', '4%')
                  ->orWhere('cc.codigo', 'like', '5%');
            })
            ->groupBy('cc.codigo', 'cc.nombre')
            ->orderBy('cc.codigo')
            ->get();

        $ing = $agg->filter(fn($x) => str_starts_with($x->codigo, '4'))->values();
        $gas = $agg->filter(fn($x) => str_starts_with($x->codigo, '5'))->values();

        // Ingresos: naturaleza acreedora (haber - debe)
        $totIng = $ing->sum(fn($x) => ((float)$x->haber - (float)$x->debe));
        // Gastos: naturaleza deudora (debe - haber)
        $totGas = $gas->sum(fn($x) => ((float)$x->debe - (float)$x->haber));
        $resultado = round($totIng - $totGas, 2);

        return view('admin_estados', [
            'condos'   => DB::table('condominio')->orderBy('nombre')->get(),
            'ctx'      => $cid,
            'defaults' => [
                'desde' => $d['desde'],
                'hasta' => $d['hasta'],
                'corte' => now()->toDateString(),
            ],
            'resultado' => [
                'tipo'         => 'eerr',
                'desde'        => $d['desde'],
                'hasta'        => $d['hasta'],
                'ingresos'     => $ing,
                'gastos'       => $gas,
                'totIngresos'  => $totIng,
                'totGastos'    => $totGas,
                'resultado'    => $resultado,
                'id_condominio'=> $cid,
            ],
        ]);
    }

    public function eerrCsv(Request $r)
    {
        $d = $r->validate([
            'desde'         => ['required','date'],
            'hasta'         => ['required','date','after_or_equal:desde'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid = $d['id_condominio'] ?? session('ctx_condo_id');

        $d1 = $d['desde'].' 00:00:00';
        $d2 = $d['hasta'].' 23:59:59';

        $rows = DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
            ->selectRaw('
                cc.codigo,
                cc.nombre,
                ROUND(SUM(l.debe),2)  as deb,
                ROUND(SUM(l.haber),2) as hab
            ')
            ->whereBetween('l.fecha', [$d1, $d2])
            ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
            ->where(function($q){
                $q->where('cc.codigo','like','4%')
                  ->orWhere('cc.codigo','like','5%');
            })
            ->groupBy('cc.codigo','cc.nombre')
            ->orderBy('cc.codigo')
            ->get();

        $lines = [['Cuenta','Nombre','Tipo','Monto']];
        foreach ($rows as $x) {
            $tipo  = str_starts_with($x->codigo,'4') ? 'Ingreso' : 'Gasto';
            $monto = str_starts_with($x->codigo,'4')
                ? ((float)$x->hab - (float)$x->deb)
                : ((float)$x->deb - (float)$x->hab);
            $lines[] = [
                $x->codigo,
                $x->nombre,
                $tipo,
                number_format($monto, 2, '.', ''),
            ];
        }

        return response($this->toCsv($lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="eerr_'.$d['desde'].'_'.$d['hasta'].'.csv"',
        ]);
    }

    /* ======================= BALANCE GENERAL ======================= */
    public function balance(Request $r)
    {
        $d = $r->validate([
            'corte'         => ['required','date'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid = $d['id_condominio'] ?? session('ctx_condo_id');

        $hasta = $d['corte'].' 23:59:59';

        $agg = DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
            ->selectRaw('
                cc.codigo,
                cc.nombre,
                ROUND(SUM(l.debe - l.haber),2) as saldo
            ')
            ->where('l.fecha', '<=', $hasta)
            ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
            ->groupBy('cc.codigo','cc.nombre')
            ->orderBy('cc.codigo')
            ->get();

        $act = $agg->filter(fn($x) => str_starts_with($x->codigo,'1'))->values();
        $pas = $agg->filter(fn($x) => str_starts_with($x->codigo,'2'))->values();
        $pat = $agg->filter(fn($x) => str_starts_with($x->codigo,'3'))->values();

        // Totales (saldo deudor positivo en Activo; Pasivo/Patrimonio acreedor positivo => invertimos signo)
        $totAct = round($act->sum(fn($x) =>  $x->saldo), 2);
        $totPas = round($pas->sum(fn($x) => -$x->saldo), 2);
        $totPat = round($pat->sum(fn($x) => -$x->saldo), 2);

        $equilibrio = round($totAct - ($totPas + $totPat), 2);

        return view('admin_estados', [
            'condos'   => DB::table('condominio')->orderBy('nombre')->get(),
            'ctx'      => $cid,
            'defaults' => [
                'corte' => $d['corte'],
                'desde' => now()->startOfMonth()->toDateString(),
                'hasta' => now()->toDateString(),
            ],
            'resultado' => [
                'tipo'        => 'balance',
                'corte'       => $d['corte'],
                'activos'     => $act,
                'pasivos'     => $pas,
                'patrimonio'  => $pat,
                'totAct'      => $totAct,
                'totPas'      => $totPas,
                'totPat'      => $totPat,
                'equilibrio'  => $equilibrio,
                'id_condominio' => $cid,
            ],
        ]);
    }

    public function balanceCsv(Request $r)
    {
        $d = $r->validate([
            'corte'         => ['required','date'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid   = $d['id_condominio'] ?? session('ctx_condo_id');
        $hasta = $d['corte'].' 23:59:59';

        $agg = DB::table('libro_movimiento as l')
            ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
            ->selectRaw('
                cc.codigo,
                cc.nombre,
                ROUND(SUM(l.debe - l.haber),2) as saldo
            ')
            ->where('l.fecha', '<=', $hasta)
            ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
            ->groupBy('cc.codigo','cc.nombre')
            ->orderBy('cc.codigo')
            ->get();

        $lines = [['Cuenta','Nombre','Grupo','Saldo']];
        foreach ($agg as $x) {
            $g = str_starts_with($x->codigo,'1') ? 'Activo'
               : (str_starts_with($x->codigo,'2') ? 'Pasivo'
               : (str_starts_with($x->codigo,'3') ? 'Patrimonio' : 'Otro'));
            $lines[] = [
                $x->codigo,
                $x->nombre,
                $g,
                number_format($x->saldo, 2, '.', ''),
            ];
        }

        return response($this->toCsv($lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="balance_'.$d['corte'].'.csv"',
        ]);
    }

    /* ======================= COMPARATIVO (dos periodos) ======================= */
    public function comparativo(Request $r)
    {
        $d = $r->validate([
            'desde1'        => ['required','date'],
            'hasta1'        => ['required','date','after_or_equal:desde1'],
            'desde2'        => ['required','date'],
            'hasta2'        => ['required','date','after_or_equal:desde2'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);

        $cid  = $d['id_condominio'] ?? session('ctx_condo_id');
        $rows = $this->comparativoBuild($d['desde1'], $d['hasta1'], $d['desde2'], $d['hasta2'], $cid);

        return view('admin_estados', [
            'condos'   => DB::table('condominio')->orderBy('nombre')->get(),
            'ctx'      => $cid,
            'defaults' => [
                'desde'  => $d['desde1'],
                'hasta'  => $d['hasta1'],
                'corte'  => now()->toDateString(),
                'desde2' => $d['desde2'],
                'hasta2' => $d['hasta2'],
            ],
            'resultado' => [
                'tipo'   => 'comparativo',
                'desde1' => $d['desde1'], 'hasta1' => $d['hasta1'],
                'desde2' => $d['desde2'], 'hasta2' => $d['hasta2'],
                'rows'   => $rows,
                'id_condominio' => $cid,
            ],
        ]);
    }

    public function comparativoCsv(Request $r)
    {
        $d = $r->validate([
            'desde1'        => ['required','date'],
            'hasta1'        => ['required','date','after_or_equal:desde1'],
            'desde2'        => ['required','date'],
            'hasta2'        => ['required','date','after_or_equal:desde2'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $cid  = $d['id_condominio'] ?? session('ctx_condo_id');
        $rows = $this->comparativoBuild($d['desde1'], $d['hasta1'], $d['desde2'], $d['hasta2'], $cid);

        $lines = [[
            'Cuenta','Nombre',
            'Debe(1)','Haber(1)','Saldo(1)',
            'Debe(2)','Haber(2)','Saldo(2)',
            'Var saldo'
        ]];

        foreach ($rows as $r) {
            $lines[] = [
                $r['codigo'], $r['nombre'],
                number_format($r['debe1'],  2, '.', ''),
                number_format($r['haber1'], 2, '.', ''),
                number_format($r['saldo1'], 2, '.', ''),
                number_format($r['debe2'],  2, '.', ''),
                number_format($r['haber2'], 2, '.', ''),
                number_format($r['saldo2'], 2, '.', ''),
                number_format($r['var_saldo'], 2, '.', ''),
            ];
        }

        return response($this->toCsv($lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="balance_comparativo.csv"',
        ]);
    }

    private function comparativoBuild(string $desde1, string $hasta1, string $desde2, string $hasta2, ?int $cid): array
    {
        $agg = function (string $d, string $h) use ($cid) {
            $d1 = $d.' 00:00:00';
            $d2 = $h.' 23:59:59';

            return DB::table('libro_movimiento as l')
                ->join('cuenta_contable as cc', 'cc.id_cta_contable', '=', 'l.id_cta_contable')
                ->selectRaw('
                    cc.codigo,
                    cc.nombre,
                    ROUND(SUM(l.debe),2)  as debe,
                    ROUND(SUM(l.haber),2) as haber,
                    ROUND(SUM(l.debe - l.haber),2) as saldo
                ')
                ->whereBetween('l.fecha', [$d1, $d2])
                ->when($cid, fn($q) => $q->where('l.id_condominio', $cid))
                ->groupBy('cc.codigo','cc.nombre')
                ->get()
                ->keyBy('codigo')
                ->all();
        };

        $p1 = $agg($desde1, $hasta1);
        $p2 = $agg($desde2, $hasta2);

        $codes = array_unique(array_merge(array_keys($p1), array_keys($p2)));
        sort($codes, SORT_NATURAL);

        $rows = [];
        foreach ($codes as $code) {
            $a = $p1[$code] ?? null;
            $b = $p2[$code] ?? null;

            $nombre = $a->nombre ?? ($b->nombre ?? '');
            $debe1  = $a->debe  ?? 0;   $haber1 = $a->haber ?? 0;   $saldo1 = $a->saldo ?? 0;
            $debe2  = $b->debe  ?? 0;   $haber2 = $b->haber ?? 0;   $saldo2 = $b->saldo ?? 0;

            $rows[] = [
                'codigo'    => $code,
                'nombre'    => $nombre,
                'debe1'     => $debe1,   'haber1' => $haber1, 'saldo1' => $saldo1,
                'debe2'     => $debe2,   'haber2' => $haber2, 'saldo2' => $saldo2,
                'var_saldo' => round($saldo2 - $saldo1, 2),
            ];
        }

        return $rows;
    }

    /* ======================= CSV helper ======================= */
    protected function toCsv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
        rewind($out);
        return stream_get_contents($out);
    }
}
