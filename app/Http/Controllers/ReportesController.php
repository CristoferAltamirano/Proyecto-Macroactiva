<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Gasto;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function morosidad()
    {
        $unidadesConDeuda = Unidad::whereHas('cobros', function ($query) {
            $query->where('estado', 'pendiente');
        })->with(['cobros' => function ($query) {
            $query->where('estado', 'pendiente');
        }])->get();

        $reporte = $unidadesConDeuda->map(function ($unidad) {
            $totalDeuda = $unidad->cobros->sum('monto_total');
            return [
                'unidad' => $unidad,
                'total_deuda' => $totalDeuda,
            ];
        });

        return view('reportes.morosidad', ['reporte' => $reporte]);
    }

    public function gastosMensuales(Request $request)
    {
        $request->validate([
            'mes' => 'nullable|integer|between:1,12',
            'ano' => 'nullable|integer|min:2000',
        ]);

        $mes = $request->input('mes', now()->month);
        $ano = $request->input('ano', now()->year);

        $gastos = Gasto::whereYear('fecha_gasto', $ano)
            ->whereMonth('fecha_gasto', $mes)
            ->get();

        $totalGastos = $gastos->sum('monto');

        return view('reportes.gastos', [
            'gastos' => $gastos,
            'totalGastos' => $totalGastos,
            'mes' => $mes,
            'ano' => $ano,
        ]);
    }

    public function panel()
    {
        // Fechas/periodos sugeridos por defecto
        $hoy = now();
        $periodoActual = $hoy->format('Ym');
        $periodoHace6 = $hoy->copy()->subMonths(5)->format('Ym');

        // Para filtros de condo en el panel
        $condos = DB::table('condominio')->orderBy('nombre')->get();

        return view('admin_reportes', [
            'condos' => $condos,
            'defaults' => [
                'corte' => $periodoActual,
                'desde' => $periodoHace6,
                'hasta' => $periodoActual,
            ],
            'resultado' => null,
        ]);
    }

    /** ============ ANTIGÜEDAD (detalle por unidad/periodo) ============ */
    public function antiguedad(Request $r)
    {
        $d = $r->validate([
            'corte' => ['required','regex:/^[0-9]{6}$/'],
            'id_condominio' => ['nullable','integer','min:1']
        ]);
        $corte = $d['corte'];
        $condo = $d['id_condominio'] ?? null;

        // Saldo por cobro hasta corte
        $rows = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->when($condo, fn($q)=>$q->where('g.id_condominio',$condo))
            ->where('c.saldo','>',0)
            ->where('c.periodo','<=',$corte)
            ->selectRaw("
                c.id_cobro, c.id_unidad, c.periodo, c.saldo,
                u.codigo as unidad, co.nombre as condominio,
                g.id_condominio
            ")
            ->orderBy('co.nombre')->orderBy('u.codigo')->orderBy('c.periodo')
            ->get();

        // Bucketización por antigüedad (en meses contra "corte")
        $corteDate = \DateTime::createFromFormat('Ym',''.$corte);
        $out = [];
        $totales = ['0-30'=>0,'31-60'=>0,'61-90'=>0,'91-180'=>0,'181-360'=>0,'361+'=>0,'TOTAL'=>0];

        foreach ($rows as $row) {
            $pDate = \DateTime::createFromFormat('Ym',''.$row->periodo);
            // Diferencia en meses
            $diff = ($corteDate->format('Y') - $pDate->format('Y'))*12 + ($corteDate->format('n') - $pDate->format('n'));
            $bucket = self::bucketMeses($diff);

            $out[] = [
                'condominio' => $row->condominio,
                'unidad'     => $row->unidad,
                'periodo'    => $row->periodo,
                'meses'      => $diff,
                'bucket'     => $bucket,
                'saldo'      => (float)$row->saldo,
            ];

            $totales[$bucket] += (float)$row->saldo;
            $totales['TOTAL'] += (float)$row->saldo;
        }

        return view('admin_reportes', [
            'condos'    => DB::table('condominio')->orderBy('nombre')->get(),
            'defaults'  => ['corte'=>$corte, 'desde'=>now()->subMonths(5)->format('Ym'), 'hasta'=>now()->format('Ym')],
            'resultado' => ['tipo'=>'antiguedad','rows'=>$out,'totales'=>$totales,'corte'=>$corte,'id_condominio'=>$condo],
        ]);
    }

    public function antiguedadCsv(Request $r)
    {
        $d = $r->validate([
            'corte' => ['required','regex:/^[0-9]{6}$/'],
            'id_condominio' => ['nullable','integer','min:1']
        ]);
        $corte = $d['corte'];
        $condo = $d['id_condominio'] ?? null;

        $rows = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->when($condo, fn($q)=>$q->where('g.id_condominio',$condo))
            ->where('c.saldo','>',0)
            ->where('c.periodo','<=',$corte)
            ->selectRaw("co.nombre as condominio, u.codigo as unidad, c.periodo, c.saldo")
            ->orderBy('co.nombre')->orderBy('u.codigo')->orderBy('c.periodo')
            ->get();

        $corteDate = \DateTime::createFromFormat('Ym',''.$corte);
        $lines = [];
        $lines[] = ['Condominio','Unidad','Periodo','Meses','Bucket','Saldo'];

        foreach ($rows as $row) {
            $pDate  = \DateTime::createFromFormat('Ym',''.$row->periodo);
            $diff = ($corteDate->format('Y') - $pDate->format('Y'))*12 + ($corteDate->format('n') - $pDate->format('n'));
            $bucket = self::bucketMeses($diff);
            $lines[] = [$row->condominio, $row->unidad, $row->periodo, $diff, $bucket, number_format((float)$row->saldo,2,'.','')];
        }

        $csv = self::toCsv($lines);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="antiguedad_'.$corte.'.csv"',
        ]);
    }

    /** ============ ANTIGÜEDAD PIVOT (por condominio) ============ */
    public function antiguedadPivot(Request $r)
    {
        $d = $r->validate(['corte'=>['required','regex:/^[0-9]{6}$/']]);
        $corte = $d['corte'];
        $rows = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->where('c.saldo','>',0)->where('c.periodo','<=',$corte)
            ->selectRaw("co.nombre as condominio, c.periodo, c.saldo")
            ->orderBy('co.nombre')->orderBy('c.periodo')->get();

        $corteDate = \DateTime::createFromFormat('Ym',''.$corte);
        $pivot = []; $TOTAL = ['0-30'=>0,'31-60'=>0,'61-90'=>0,'91-180'=>0,'181-360'=>0,'361+'=>0,'TOTAL'=>0];

        foreach ($rows as $r2) {
            $pDate = \DateTime::createFromFormat('Ym',''.$r2->periodo);
            $diff  = ($corteDate->format('Y') - $pDate->format('Y'))*12 + ($corteDate->format('n') - $pDate->format('n'));
            $bucket = self::bucketMeses($diff);

            if (!isset($pivot[$r2->condominio])) {
                $pivot[$r2->condominio] = ['0-30'=>0,'31-60'=>0,'61-90'=>0,'91-180'=>0,'181-360'=>0,'361+'=>0,'TOTAL'=>0];
            }
            $pivot[$r2->condominio][$bucket] += (float)$r2->saldo;
            $pivot[$r2->condominio]['TOTAL'] += (float)$r2->saldo;

            $TOTAL[$bucket] += (float)$r2->saldo;
            $TOTAL['TOTAL'] += (float)$r2->saldo;
        }

        return view('admin_reportes', [
            'condos'    => DB::table('condominio')->orderBy('nombre')->get(),
            'defaults'  => ['corte'=>$corte, 'desde'=>now()->subMonths(5)->format('Ym'), 'hasta'=>now()->format('Ym')],
            'resultado' => ['tipo'=>'antiguedad_pivot','pivot'=>$pivot,'total'=>$TOTAL,'corte'=>$corte],
        ]);
    }

    public function antiguedadPivotCsv(Request $r)
    {
        $d = $r->validate(['corte'=>['required','regex:/^[0-9]{6}$/']]);
        $corte = $d['corte'];

        $rows = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->where('c.saldo','>',0)->where('c.periodo','<=',$corte)
            ->selectRaw("co.nombre as condominio, c.periodo, c.saldo")
            ->orderBy('co.nombre')->orderBy('c.periodo')->get();

        $corteDate = \DateTime::createFromFormat('Ym',''.$corte);
        $agg = [];

        foreach ($rows as $r2) {
            $pDate = \DateTime::createFromFormat('Ym',''.$r2->periodo);
            $diff  = ($corteDate->format('Y') - $pDate->format('Y'))*12 + ($corteDate->format('n') - $pDate->format('n'));
            $bucket = self::bucketMeses($diff);

            if (!isset($agg[$r2->condominio])) {
                $agg[$r2->condominio] = ['0-30'=>0,'31-60'=>0,'61-90'=>0,'91-180'=>0,'181-360'=>0,'361+'=>0,'TOTAL'=>0];
            }
            $agg[$r2->condominio][$bucket] += (float)$r2->saldo;
            $agg[$r2->condominio]['TOTAL'] += (float)$r2->saldo;
        }

        $lines = [];
        $lines[] = ['Condominio','0-30','31-60','61-90','91-180','181-360','361+','TOTAL'];
        foreach ($agg as $condo => $vals) {
            $lines[] = [
                $condo,
                number_format($vals['0-30'],2,'.',''),
                number_format($vals['31-60'],2,'.',''),
                number_format($vals['61-90'],2,'.',''),
                number_format($vals['91-180'],2,'.',''),
                number_format($vals['181-360'],2,'.',''),
                number_format($vals['361+'],2,'.',''),
                number_format($vals['TOTAL'],2,'.',''),
            ];
        }

        $csv = self::toCsv($lines);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="antiguedad_pivot_'.$corte.'.csv"',
        ]);
    }

    /** ============ RECAUDACIÓN (por periodo y método) ============ */
    public function recaudacion(Request $r)
    {
        $d = $r->validate([
            'desde' => ['required','regex:/^[0-9]{6}$/'],
            'hasta' => ['required','regex:/^[0-9]{6}$/'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $desde = $d['desde']; $hasta = $d['hasta']; $condo = $d['id_condominio'] ?? null;

        // Determinamos "periodo_pago": usa p.periodo si existe, si no, YYYYMM(fecha_pago)
        $rows = DB::table('pago as p')
            ->leftJoin('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as m','m.id_metodo_pago','=','p.id_metodo_pago')
            ->when($condo, fn($q)=>$q->where('g.id_condominio',$condo))
            ->selectRaw("
                c.nombre as condominio,
                COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) as periodo_pago,
                m.nombre as metodo,
                SUM(p.monto) as total
            ")
            ->whereRaw("COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) >= ?", [$desde])
            ->whereRaw("COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) <= ?", [$hasta])
            ->groupBy('condominio','periodo_pago','metodo')
            ->orderBy('condominio')->orderBy('periodo_pago')->orderBy('metodo')
            ->get();

        // Totales por periodo
        $byPeriodo = [];
        foreach ($rows as $r2) {
            $key = $r2->condominio.'|'.$r2->periodo_pago;
            if (!isset($byPeriodo[$key])) $byPeriodo[$key] = ['condominio'=>$r2->condominio,'periodo'=>$r2->periodo_pago,'metodos'=>[],'total'=>0];
            $byPeriodo[$key]['metodos'][$r2->metodo ?? '—'] = (float)$r2->total;
            $byPeriodo[$key]['total'] += (float)$r2->total;
        }

        return view('admin_reportes', [
            'condos'    => DB::table('condominio')->orderBy('nombre')->get(),
            'defaults'  => ['corte'=>now()->format('Ym'),'desde'=>$desde,'hasta'=>$hasta],
            'resultado' => ['tipo'=>'recaudacion','rows'=>$rows,'porPeriodo'=>array_values($byPeriodo),'desde'=>$desde,'hasta'=>$hasta,'id_condominio'=>$condo],
        ]);
    }

    public function recaudacionCsv(Request $r)
    {
        $d = $r->validate([
            'desde' => ['required','regex:/^[0-9]{6}$/'],
            'hasta' => ['required','regex:/^[0-9]{6}$/'],
            'id_condominio' => ['nullable','integer','min:1'],
        ]);
        $desde = $d['desde']; $hasta = $d['hasta']; $condo = $d['id_condominio'] ?? null;

        $rows = DB::table('pago as p')
            ->leftJoin('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as m','m.id_metodo_pago','=','p.id_metodo_pago')
            ->when($condo, fn($q)=>$q->where('g.id_condominio',$condo))
            ->selectRaw("
                c.nombre as condominio,
                COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) as periodo_pago,
                m.nombre as metodo,
                SUM(p.monto) as total
            ")
            ->whereRaw("COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) >= ?", [$desde])
            ->whereRaw("COALESCE(p.periodo, DATE_FORMAT(p.fecha_pago, '%Y%m')) <= ?", [$hasta])
            ->groupBy('condominio','periodo_pago','metodo')
            ->orderBy('condominio')->orderBy('periodo_pago')->orderBy('metodo')
            ->get();

        $lines = [];
        $lines[] = ['Condominio','Periodo','Método','Total'];
        foreach ($rows as $r2) {
            $lines[] = [$r2->condominio, $r2->periodo_pago, $r2->metodo ?? '—', number_format((float)$r2->total,2,'.','')];
        }

        $csv = self::toCsv($lines);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="recaudacion_'.$desde.'_'.$hasta.'.csv"',
        ]);
    }

    /* ----------------- Helpers ----------------- */

    private static function bucketMeses(int $meses): string
    {
        if ($meses <= 0) return '0-30';
        if ($meses == 1) return '31-60';
        if ($meses == 2) return '61-90';
        if ($meses <= 6) return '91-180';
        if ($meses <= 12) return '181-360';
        return '361+';
    }

    private static function toCsv(array $rows): string
    {
        $f = fopen('php://temp','r+');
        // BOM UTF-8 para Excel
        fwrite($f, "\xEF\xBB\xBF");
        foreach ($rows as $r) fputcsv($f, $r);
        rewind($f);
        return stream_get_contents($f);
    }
}
