<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class ConciliacionController extends Controller
{
    /** ==================== PANEL ==================== */
    public function panel()
    {
        $list = DB::table('conciliacion as c')
            ->leftJoin('condominio as k','k.id_condominio','=','c.id_condominio')
            ->select('c.*','k.nombre as condominio')
            ->orderByDesc('c.id_conciliacion')
            ->limit(30)->get();

        return view('admin_conciliacion', ['list'=>$list]);
    }

    /** ==================== UPLOAD + AUTO ==================== */
    public function upload(Request $r)
    {
        $r->validate([
            'archivo'=>['required','file','mimes:csv,txt','max:20480'],
            'id_condominio'=>['nullable','integer','min:1']
        ]);

        $cid = $r->input('id_condominio');

        // 1) Guardar archivo
        $path = $r->file('archivo')->store('conciliacion', 'local');

        // 2) Crear cabecera
        $idConc = DB::table('conciliacion')->insertGetId([
            'id_condominio'=>$cid,
            'archivo_path'=>$path,
            'items_qty'=>0,
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        // 3) Parse CSV
        $raw   = Storage::disk('local')->get($path);
        $lines = preg_split("/\r\n|\n|\r/", $raw);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line)==='') continue;
            $parts = str_getcsv($line, ';');
            if (count($parts) < 2) $parts = str_getcsv($line, ',');
            if (count($parts) < 1) continue;
            $rows[] = $parts;
        }

        // 4) Detectar encabezado y mapear columnas
        $idx = ['fecha'=>0,'monto'=>1,'glosa'=>2,'referencia'=>3]; // fallback
        if (!empty($rows)) {
            $head = array_map(fn($v)=>mb_strtolower(trim($v)), $rows[0]);
            $names = ['fecha','monto','glosa','referencia','detalle','descripcion','desc','ref','nref','documento'];
            $foundHeader = false;
            foreach ($head as $k=>$h) {
                if (in_array($h, $names, true)) { $foundHeader = true; break; }
            }
            if ($foundHeader) {
                // map flexible
                $map = [
                    'fecha'      => ['fecha','date','fch','fec'],
                    'monto'      => ['monto','amount','valor','importe','valor abonado','abono','cargo'],
                    'glosa'      => ['glosa','detalle','descripcion','desc','concepto'],
                    'referencia' => ['referencia','ref','nref','documento','doc','id transaccion','txid'],
                ];
                $idx = [];
                foreach ($map as $key=>$alts) {
                    $idx[$key] = $this->findIndex($head, $alts, $key);
                }
                array_shift($rows); // quita encabezado
            }
        }

        // 5) Normalizadores
        $parseMonto = fn($s) => $this->normMonto($s);
        $parseFecha = fn($s) => $this->normFecha($s);

        // 6) Insertar ítems + auto conciliar
        $ins = 0; $autoApplied = 0; $autoCreated = 0; $suggested = 0;

        foreach ($rows as $cols) {
            $fecha = $parseFecha($cols[$idx['fecha']] ?? null);
            $monto = $parseMonto($cols[$idx['monto']] ?? '0');
            $glosa = trim((string)($cols[$idx['glosa']] ?? ''));
            $ref   = trim((string)($cols[$idx['referencia']] ?? ''));

            // omitimos líneas sin monto
            if (abs($monto) < 0.01) continue;

            // hash idempotente
            $ihash = $this->makeHash($cid, $fecha, $monto, $glosa, $ref);

            // evitar duplicados dentro de la misma conciliación
            $dup = DB::table('conciliacion_item')
                ->where('id_conciliacion',$idConc)
                ->where('fecha',$fecha)
                ->where('monto',$monto)
                ->where('glosa',$glosa)
                ->where('referencia',$ref)
                ->exists();

            if ($dup) continue;

            $payload = [
                'id_conciliacion'=>$idConc,
                'fecha'=>$fecha,
                'monto'=>$monto,
                'glosa'=>$glosa,
                'referencia'=>$ref,
                'estado'=>'pendiente',
                'created_at'=>now(),
                'updated_at'=>now(),
            ];
            if (Schema::hasColumn('conciliacion_item','hash')) {
                $payload['hash'] = $ihash;
            }

            $idItem = DB::table('conciliacion_item')->insertGetId($payload);
            $ins++;

            // AUTO: conciliar item
            $result = $this->autoConciliarItem($idItem, $cid);
            if ($result['aplicado']) $autoApplied++;
            if ($result['creado'])   $autoCreated++;
            if ($result['sugerido']) $suggested++;
        }

        // actualizar qty
        DB::table('conciliacion')->where('id_conciliacion',$idConc)->update(['items_qty'=>$ins]);

        $msg = "Archivo cargado. Items: {$ins}. Auto-aplicados: {$autoApplied}. Auto-creados: {$autoCreated}.";
        if ($suggested>0) $msg .= " Sugeridos: {$suggested}.";
        return redirect()->route('admin.conciliacion.detalle', $idConc)->with('ok', $msg);
    }

    /** ==================== DETALLE ==================== */
    public function detalle($id)
    {
        $conc = DB::table('conciliacion')->where('id_conciliacion',$id)->first();
        abort_unless($conc, 404);

        $items = DB::table('conciliacion_item')->where('id_conciliacion',$id)
            ->orderBy('estado')->orderByDesc('fecha')->get();

        return view('admin_conciliacion_detalle', [
            'conc'=>$conc, 'items'=>$items
        ]);
    }

    /** ==================== ACCIONES MANUALES ==================== */
    public function aplicarExistente($idItem, Request $r)
    {
        $d = $r->validate([
            'id_pago'=>['required','integer','min:1']
        ]);

        $item = DB::table('conciliacion_item')->where('id_item',$idItem)->first();
        abort_unless($item, 404);

        $pago = DB::table('pago')->where('id_pago',$d['id_pago'])->first();
        if (!$pago) return back()->withErrors(['id_pago'=>'Pago no encontrado']);

        DB::table('conciliacion_item')->where('id_item',$idItem)->update([
            'estado'=>'aplicado','id_pago'=>$pago->id_pago,'updated_at'=>now()
        ]);

        return back()->with('ok','Item conciliado con pago existente.');
    }

    public function crearPago($idItem, Request $r)
    {
        $d = $r->validate([
            'id_unidad'=>['required','integer','min:1'],
            'periodo'  =>['nullable','regex:/^[0-9]{6}$/'],
            'metodo'   =>['nullable','integer','min:1'], // id_metodo_pago
            'fecha'    =>['nullable','date'],
        ]);

        $item = DB::table('conciliacion_item')->where('id_item',$idItem)->first();
        abort_unless($item, 404);

        $idMetodo = $d['metodo'] ?? DB::table('cat_metodo_pago')->where('codigo','transferencia')->value('id_metodo_pago');
        $fecha    = $d['fecha'] ?? ($item->fecha ?: now()->toDateString());

        DB::beginTransaction();
        try {
            $idPago = DB::table('pago')->insertGetId([
                'id_unidad'=>$d['id_unidad'],
                'fecha_pago'=>$fecha.' 12:00:00',
                'periodo'=>$d['periodo'] ?? null,
                'tipo'=>'normal',
                'monto'=>$item->monto,
                'id_metodo_pago'=>$idMetodo,
                'ref_externa'=>'CONC#'.$idItem,
                'observacion'=>substr('Conciliación #'.$item->id_conciliacion.' - '.$item->glosa,0,300)
            ]);

            $this->aplicarPagoFIFO($idPago, (int)$d['id_unidad'], (float)$item->monto);

            DB::table('conciliacion_item')->where('id_item',$idItem)->update([
                'estado'=>'aplicado','id_pago'=>$idPago,'updated_at'=>now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['crear'=>'Error creando/aplicando pago: '.$e->getMessage()]);
        }

        return back()->with('ok','Pago creado y aplicado correctamente.');
    }

    /** ==================== HELPERS AUTO ==================== */

    private function findIndex(array $header, array $alts, string $fallbackKey): int
    {
        foreach ($alts as $a) {
            $k = array_search($a, $header, true);
            if ($k !== false) return (int)$k;
        }
        // si no se encontró, intenta por contains (más laxo)
        foreach ($header as $k=>$h) {
            foreach ($alts as $a) {
                if (str_contains($h, $a)) return (int)$k;
            }
        }
        // fallback por posición clásica
        return ['fecha'=>0,'monto'=>1,'glosa'=>2,'referencia'=>3][$fallbackKey] ?? 0;
        }

    private function normMonto($s): float
    {
        $s = (string)($s ?? '');
        $s = trim(str_replace(["\xc2\xa0",'$',' '], '', $s));
        // soporta 1.234,56 o 1,234.56 o 1234.56
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) return 0.0;
        return round((float)$s, 2);
    }

    private function normFecha($s): ?string
    {
        $s = trim((string)($s ?? ''));
        if ($s==='') return null;
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s)) return $s;
        if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        if (preg_match('~^(\d{2})-(\d{2})-(\d{4})$~', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        // último recurso: strtotime
        $t = strtotime($s);
        return $t ? date('Y-m-d', $t) : null;
    }

    private function makeHash($cid, $fecha, $monto, $glosa, $ref): string
    {
        return sha1(trim((string)$cid).'|'.trim((string)$fecha).'|'.number_format((float)$monto,2,'.','').'|'.mb_strtolower(trim((string)$glosa)).'|'.mb_strtolower(trim((string)$ref)));
    }

    private function guessUnidadFromText(?string $text, ?int $idCondominio=null): ?int
    {
        $text = mb_strtoupper(trim((string)$text));
        if ($text==='') return null;

        // Patrones comunes: U123, UNI-45, UNI45, DEPTO 1204, DEPTO1204
        if (preg_match('/\bU(\d+)\b/', $text, $m)) {
            $id = (int)$m[1];
            if ($id>0 && DB::table('unidad')->where('id_unidad',$id)->exists()) return $id;
        }
        if (preg_match('/\bUNI-?(\d+)\b/', $text, $m)) {
            $id = (int)$m[1];
            if ($id>0 && DB::table('unidad')->where('id_unidad',$id)->exists()) return $id;
        }
        if (preg_match('/\bDEPTO\s*([A-Z0-9\-]+)\b/', $text, $m)) {
            $code = $m[1];
            $q = DB::table('unidad')->whereRaw('UPPER(codigo)=?', [mb_strtoupper($code)]);
            if ($idCondominio) {
                $q->join('grupo as g','g.id_grupo','=','unidad.id_grupo')
                  ->where('g.id_condominio',$idCondominio)
                  ->select('unidad.id_unidad');
            }
            $id = $q->value('unidad.id_unidad');
            if ($id) return (int)$id;
        }
        // Busca por código de unidad en todo el texto (palabra exacta)
        $q = DB::table('unidad');
        if ($idCondominio) {
            $q->join('grupo as g','g.id_grupo','=','unidad.id_grupo')
              ->where('g.id_condominio',$idCondominio)
              ->select('unidad.id_unidad','unidad.codigo');
        } else {
            $q->select('id_unidad','codigo');
        }
        $cands = $q->limit(2000)->get();
        foreach ($cands as $u) {
            if (!$u->codigo) continue;
            $code = mb_strtoupper($u->codigo);
            if (preg_match('/\b'.preg_quote($code,'/').'\b/', $text)) {
                return (int)$u->id_unidad;
            }
        }
        return null;
    }

    private function matchPagoByMontoFecha(float $monto, ?string $fecha, ?int $idCondominio=null, ?int $idUnidadPref=null): array
    {
        $from = $fecha ? date('Y-m-d', strtotime($fecha.' -2 days')) : null;
        $to   = $fecha ? date('Y-m-d', strtotime($fecha.' +2 days')) : null;

        $base = DB::table('pago as p')
            ->leftJoin('cat_metodo_pago as mp','mp.id_metodo_pago','=','p.id_metodo_pago')
            ->leftJoin('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->when($idCondominio, fn($q)=>$q->where('g.id_condominio',$idCondominio))
            ->where('p.monto', round($monto,2));

        if ($from && $to) {
            $base->whereBetween(DB::raw('DATE(p.fecha_pago)'), [$from, $to]);
        }

        // Evita pagar duplicado del mismo conciliación_item si ya está aplicado
        $base->whereNotIn('p.id_pago', function($q){
            $q->select('id_pago')->from('conciliacion_item')->whereNotNull('id_pago');
        });

        $cand = $base->select('p.*','mp.codigo as metodo_codigo','g.id_condominio')->limit(10)->get();

        // Si hay preferencia por unidad, prioriza
        if ($idUnidadPref) {
            $pref = $cand->where('id_unidad', $idUnidadPref)->values();
            if ($pref->count() === 1) return [$pref[0]];
            if ($pref->count() > 1)   return $pref->all();
        }

        return $cand->all();
    }

    private function aplicarPagoFIFO(int $idPago, int $idUnidad, float $monto): void
    {
        $restante = $monto;
        $cobros = DB::table('cobro')
            ->where('id_unidad',$idUnidad)
            ->where('saldo','>',0)
            ->orderBy('periodo')
            ->lockForUpdate()
            ->get();

        $touched = [];
        foreach ($cobros as $c) {
            if ($restante <= 0) break;
            $aplicar = min($restante, (float)$c->saldo);
            DB::table('pago_aplicacion')->insert([
                'id_pago'=>$idPago,
                'id_cobro'=>$c->id_cobro,
                'monto_aplicado'=>$aplicar,
            ]);
            $restante = round($restante - $aplicar, 2);
            $touched[] = (int)$c->id_cobro;
        }

        // Recalcular totales
        foreach ($touched as $idCobro) {
            try { \App\Services\CobroService::recalcularTotales($idCobro); } catch (\Throwable $e) {}
        }
    }

    private function setSuggestion(int $idItem, int $idPago, float $score=0.5): void
    {
        if (Schema::hasColumn('conciliacion_item','sugerido_id_pago')) {
            DB::table('conciliacion_item')->where('id_item',$idItem)->update([
                'sugerido_id_pago'=>$idPago,
                'match_score'=>$score,
                'updated_at'=>now(),
            ]);
        }
    }

    /**
     * Intenta auto-conciliar 1 item: (1) match pago por monto/fecha (con preferencia de unidad),
     * (2) si no hay pago y se detecta unidad, crea pago transferencia y aplica FIFO.
     * Retorna ['aplicado'=>bool,'creado'=>bool,'sugerido'=>bool]
     */
    private function autoConciliarItem(int $idItem, ?int $idCondominio=null): array
    {
        $out = ['aplicado'=>false,'creado'=>false,'sugerido'=>false];

        $item = DB::table('conciliacion_item')->where('id_item',$idItem)->first();
        if (!$item || (float)$item->monto === 0.0) return $out;

        // 1) Intentar inferir unidad desde glosa/ref
        $text  = trim(($item->glosa ?? '').' '.($item->referencia ?? ''));
        $idUni = $this->guessUnidadFromText($text, $idCondominio);

        // 2) Buscar pago existente por monto/fecha
        $cands = $this->matchPagoByMontoFecha((float)$item->monto, $item->fecha, $idCondominio, $idUni);

        // Heurística: prioriza pagos Webpay si la glosa huele a TBK/WEBPAY
        $hueleWebpay = (bool)preg_match('/TBK|WEBPAY|TRANSBANK/i', $text);

        if (!empty($cands)) {
            $pick = null;

            if ($idUni) {
                $uni = array_values(array_filter($cands, fn($p)=> (int)$p->id_unidad === (int)$idUni));
                if (count($uni) === 1) $pick = $uni[0];
            }

            if (!$pick && $hueleWebpay) {
                $tbk = array_values(array_filter($cands, function($p){
                    if (isset($p->metodo_codigo) && $p->metodo_codigo === 'webpay') return true;
                    if (isset($p->tipo) && (is_string($p->tipo) ? $p->tipo==='webpay' : (int)$p->tipo===2)) return true;
                    if (!empty($p->ref_externa) && preg_match('/TBK|WEBPAY|TRANSBANK/i', $p->ref_externa)) return true;
                    return false;
                }));
                if (count($tbk) === 1) $pick = $tbk[0];
            }

            if (!$pick && count($cands) === 1) {
                $pick = $cands[0];
            }

            if ($pick) {
                DB::table('conciliacion_item')->where('id_item',$idItem)->update([
                    'estado'=>'aplicado',
                    'id_pago'=>$pick->id_pago,
                    'updated_at'=>now()
                ]);
                $out['aplicado'] = true;
                return $out;
            } else {
                // dejar sugerencia si hay columna
                $this->setSuggestion($idItem, (int)$cands[0]->id_pago, 0.4);
                $out['sugerido'] = true;
                // no devolvemos aún: quizá podamos crear pago si hay unidad
            }
        }

        // 3) Si no hay pago y tenemos unidad -> crear pago transferencia y aplicar
        if ($idUni) {
            try {
                DB::beginTransaction();

                $idMetodo = DB::table('cat_metodo_pago')->where('codigo','transferencia')->value('id_metodo_pago');

                $idPago = DB::table('pago')->insertGetId([
                    'id_unidad'      => $idUni,
                    'fecha_pago'     => ($item->fecha ?: now()->toDateString()).' 12:00:00',
                    'periodo'        => null,
                    'tipo'           => 'normal',
                    'monto'          => (float)$item->monto,
                    'id_metodo_pago' => $idMetodo ?: null,
                    'ref_externa'    => 'CONC#'.$idItem,
                    'observacion'    => substr('Conciliación #'.$item->id_conciliacion.' - '.$item->glosa, 0, 300),
                ]);

                $this->aplicarPagoFIFO($idPago, (int)$idUni, (float)$item->monto);

                DB::table('conciliacion_item')->where('id_item',$idItem)->update([
                    'estado'=>'aplicado',
                    'id_pago'=>$idPago,
                    'updated_at'=>now(),
                ]);

                DB::commit();
                $out['creado'] = true;
                return $out;
            } catch (\Throwable $e) {
                DB::rollBack();
                // si falla creación, queda pendiente/sugerido
            }
        }

        return $out;
    }
}
