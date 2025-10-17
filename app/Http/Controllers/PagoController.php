<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Services\AuditoriaService;

class PagoController extends Controller
{
    /** Panel de pagos: formulario + últimos pagos */
    public function panel()
    {
        $pagos = DB::table('pago as p')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as mp','mp.id_metodo_pago','=','p.id_metodo_pago')
            ->leftJoin('comprobante_pago as cp','cp.id_pago','=','p.id_pago')
            ->select(
                'p.*',
                'u.codigo as unidad',
                'c.nombre as condominio',
                'mp.nombre as metodo',
                'cp.folio'
            )
            ->orderByDesc('p.id_pago')     // asegura que lo último aparezca primero
            ->orderByDesc('p.fecha_pago')
            ->limit(25)
            ->get();

        $metodos = DB::table('cat_metodo_pago')->orderBy('nombre')->get();

        return view('pagos_panel', compact('pagos','metodos'));
    }

    /** Registrar pago manual (política estricta + errores claros + auditoría) */
    public function store(Request $r)
    {
        // Aceptar alias id_metodo
        if (!$r->filled('id_metodo_pago') && $r->filled('id_metodo')) {
            $r->merge(['id_metodo_pago' => $r->input('id_metodo')]);
        }

        $d = $r->validate([
            'id_unidad'      => ['required','integer','min:1'],
            'periodo'        => ['nullable','regex:/^[0-9]{6}$/'],
            'monto'          => ['required','numeric','min:0.01'],
            'id_metodo_pago' => ['required','integer','min:1'],
            'ref_externa'    => ['nullable','string','max:120'],
            'observacion'    => ['nullable','string','max:300'],
        ]);

        // Unidad válida
        $unidad = DB::table('unidad')->where('id_unidad',$d['id_unidad'])->first();
        if (!$unidad) {
            return redirect()->route('pagos.panel')->with('err','Unidad no encontrada.');
        }

        // Condominio (para auditar contexto)
        $idCondo = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('u.id_unidad',$d['id_unidad'])
            ->value('g.id_condominio');

        // Método válido (fallback a efectivo si no existe)
        $metodoExiste = DB::table('cat_metodo_pago')->where('id_metodo_pago',$d['id_metodo_pago'])->exists();
        if (!$metodoExiste) {
            $fallback = DB::table('cat_metodo_pago')->where('codigo','efectivo')->value('id_metodo_pago');
            if ($fallback) $d['id_metodo_pago'] = $fallback;
            else return redirect()->route('pagos.panel')->with('err','Método de pago inválido.');
        }

        // 🚧 PRE-CHEQUEO: Período cerrado (si existen tablas de cierre)
        $per = $d['periodo'] ?? null;
        if ($per) {
            $periodoCerrado = false;

            // cierre_mes(periodo, estado='cerrado')
            if (Schema::hasTable('cierre_mes')) {
                try {
                    $periodoCerrado = DB::table('cierre_mes')
                        ->where('periodo', $per)
                        ->when(Schema::hasColumn('cierre_mes','estado'), fn($q) => $q->where('estado','cerrado'))
                        ->exists();
                } catch (\Throwable $e) {}
            }

            // cierres(periodo, cerrado=1) / estados similares
            if (!$periodoCerrado && Schema::hasTable('cierres')) {
                try {
                    $periodoCerrado = DB::table('cierres')
                        ->where('periodo',$per)
                        ->when(Schema::hasColumn('cierres','cerrado'), fn($q)=>$q->where('cerrado',1))
                        ->when(Schema::hasColumn('cierres','estado'), fn($q)=>$q->orWhere('estado','cerrado'))
                        ->exists();
                } catch (\Throwable $e) {}
            }

            if ($periodoCerrado) {
                AuditoriaService::log('pago', 0, 'RECHAZADO_PERIODO_CERRADO', [
                    'periodo' => $per,
                    'id_unidad' => (int)$d['id_unidad'],
                    'id_condominio' => $idCondo,
                    'monto' => (float)$d['monto'],
                ]);
                return redirect()->route('pagos.panel')->with(
                    'err',
                    "No se puede registrar el pago porque el período $per está CERRADO. ".
                    "Reabre el período en Cierres para imputarlo, o deja el campo «Periodo» vacío para registrarlo sin imputar."
                );
            }
        }

        // Insert robusto
        $insert = [
            'id_unidad'      => (int)$d['id_unidad'],
            'fecha_pago'     => now(),
            'periodo'        => $per,
            'monto'          => (float)$d['monto'],
            'id_metodo_pago' => (int)$d['id_metodo_pago'],
            'ref_externa'    => $d['ref_externa'] ?? null,
            'observacion'    => $d['observacion'] ?? null,
        ];

        // Campo 'tipo' (si existe) => normal
        $tipoCol = Schema::hasColumn('pago','tipo');
        $tipoSet = false;
        if ($tipoCol) {
            try { $colType = Schema::getColumnType('pago','tipo'); } catch (\Throwable $e) { $colType = 'string'; }
            if (in_array($colType, ['string','text'])) { $insert['tipo'] = 'normal'; $tipoSet = true; }
            elseif (in_array($colType, ['integer','bigint','smallint','tinyint','boolean'])) { $insert['tipo'] = 1; $tipoSet = true; }
        }

        DB::beginTransaction();
        try {
            // INSERT principal
            try {
                $idPago = DB::table('pago')->insertGetId($insert);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                Log::warning('PAGO MANUAL insert falló', ['msg' => $msg, 'payload' => $insert]);

                // Señal del trigger de período cerrado
                if (stripos($msg, 'Periodo cerrado') !== false || stripos($msg, 'SQLSTATE[45000]') !== false) {
                    DB::rollBack();
                    $perMsg = $per ?: '(sin periodo)';
                    AuditoriaService::log('pago', 0, 'RECHAZADO_PERIODO_CERRADO', [
                        'periodo' => $perMsg,
                        'id_unidad' => (int)$d['id_unidad'],
                        'id_condominio' => $idCondo,
                        'monto' => (float)$d['monto'],
                        'motor' => 'trigger_bd'
                    ]);
                    return redirect()->route('pagos.panel')->with(
                        'err',
                        "No se puede registrar el pago porque el período $perMsg está CERRADO. ".
                        "Reabre el período en Cierres para imputarlo, o deja «Periodo» vacío para registrarlo sin imputar."
                    );
                }

                // Reintento por problemas con 'tipo'
                if ($tipoCol && stripos($msg, "column 'tipo'") !== false) {
                    if ($tipoSet) {
                        if (isset($insert['tipo']) && $insert['tipo'] === 'normal') $insert['tipo'] = 'webpay';
                        elseif (isset($insert['tipo']) && (int)$insert['tipo'] === 1) $insert['tipo'] = 0;
                    } else {
                        unset($insert['tipo']);
                    }
                    $idPago = DB::table('pago')->insertGetId($insert);
                } else {
                    throw $e;
                }
            }

            // Auditoría: creación de pago
            AuditoriaService::log('pago', $idPago, 'CREAR', array_merge($insert, [
                'id_condominio' => $idCondo
            ]));

            // Asegurar comprobante
            if (Schema::hasTable('comprobante_pago')) {
                $ya = DB::table('comprobante_pago')->where('id_pago',$idPago)->exists();
                if (!$ya) {
                    $folio = 'CP-'.now()->format('Ym').'-'.$idPago;
                    DB::table('comprobante_pago')->insert([
                        'id_pago'    => $idPago,
                        'folio'      => $folio,
                        'url_pdf'    => null,
                        'emitido_at' => now(),
                    ]);
                    AuditoriaService::log('pago', $idPago, 'COMPROBANTE_CREAR', ['folio'=>$folio]);
                }
            }

            // Aplicación FIFO a cobros + recalculo saldos
            if (Schema::hasTable('cobro') && Schema::hasTable('pago_aplicacion')) {
                $restante = (float)$d['monto'];
                $cobros = DB::table('cobro')
                    ->where('id_unidad', (int)$d['id_unidad'])
                    ->where('saldo','>',0)
                    ->orderBy('periodo')
                    ->get();

                $aplics = [];
                $tocados = [];
                foreach ($cobros as $c) {
                    if ($restante <= 0) break;
                    $aplicar = min($restante, (float)$c->saldo);
                    if ($aplicar <= 0) continue;

                    DB::table('pago_aplicacion')->insert([
                        'id_pago'        => $idPago,
                        'id_cobro'       => $c->id_cobro,
                        'monto_aplicado' => $aplicar,
                    ]);

                    $aplics[] = ['id_cobro'=>$c->id_cobro, 'periodo'=>$c->periodo, 'monto'=>$aplicar];
                    $restante = round($restante - $aplicar, 2);
                    $tocados[] = (int)$c->id_cobro;
                }

                if (!empty($aplics)) {
                    AuditoriaService::log('pago', $idPago, 'APLICACION_COBROS', ['detalles'=>$aplics]);
                }

                if (!empty($tocados) && class_exists(\App\Services\CobroService::class)) {
                    foreach ($tocados as $idCobro) {
                        try { \App\Services\CobroService::recalcularTotales($idCobro); } catch (\Throwable $e) {}
                    }
                }
            }

            DB::commit();
            return redirect()->route('pagos.panel')->with('ok','Pago registrado (ID '.$idPago.').');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PAGO MANUAL ERROR', ['e'=>$e->getMessage()]);
            AuditoriaService::log('pago', 0, 'ERROR_STORE', [
                'msg'=>$e->getMessage(),
                'id_unidad'=>(int)$d['id_unidad'],
                'id_condominio'=>$idCondo,
                'periodo'=>$per,
                'monto'=>(float)$d['monto'],
            ]);
            return redirect()->route('pagos.panel')->with('err','No se pudo registrar: '.$e->getMessage());
        }
    }

    /** Botón "Aprobar demo": sólo asegura comprobante + auditoría */
    public function aprobarDemo($idPago)
    {
        $pago = DB::table('pago')->where('id_pago',$idPago)->first();
        if (!$pago) return redirect()->route('pagos.panel')->with('err','Pago no encontrado.');

        if (Schema::hasTable('comprobante_pago')) {
            $ya = DB::table('comprobante_pago')->where('id_pago',$idPago)->first();
            if (!$ya) {
                $folio = 'CP-'.now()->format('Ym').'-'.$idPago;
                DB::table('comprobante_pago')->insert([
                    'id_pago'    => $idPago,
                    'folio'      => $folio,
                    'url_pdf'    => null,
                    'emitido_at' => now(),
                ]);
                AuditoriaService::log('pago', $idPago, 'COMPROBANTE_CREAR', ['folio'=>$folio, 'via'=>'aprobar_demo']);
            }
        }

        AuditoriaService::log('pago', $idPago, 'APROBAR_DEMO', []);
        return redirect()->route('pagos.panel')->with('ok','Pago aprobado (demo) y comprobante listo.');
    }

    /** Recibo HTML (tu stack puede convertirlo a PDF) */
    public function reciboPdf($id)
    {
        $q = DB::table('pago as p')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as mp','mp.id_metodo_pago','=','p.id_metodo_pago')
            ->leftJoin('comprobante_pago as cp','cp.id_pago','=','p.id_pago')
            ->select(
                'p.*',
                'u.codigo as unidad',
                'c.nombre as condominio',
                'mp.nombre as metodo',
                'cp.folio'
            )
            ->where('p.id_pago',$id);

        if (Schema::hasTable('pasarela_tx') && Schema::hasColumn('pasarela_tx','id_pago')) {
            $q->leftJoin('pasarela_tx as tx','tx.id_pago','=','p.id_pago');
            $add = function($col, $alias) use ($q) { if (Schema::hasColumn('pasarela_tx',$col)) $q->addSelect(DB::raw("tx.$col as $alias")); };
            $add('authorization_code','tx_auth_code'); $add('auth_code','tx_auth_code');
            $add('payment_type_code','tx_paytype');    $add('tipo_pago','tx_paytype');
            $add('installments_number','tx_installments'); $add('installments','tx_installments'); $add('cuotas','tx_installments');
            $add('card_last4','tx_last4'); $add('last4','tx_last4'); $add('tarjeta_ult4','tx_last4');
        }

        $p = $q->first();
        if (!$p) abort(404);

        $auth = $p->tx_auth_code ?? null;
        if (!$auth && !empty($p->ref_externa) && preg_match('~auth:([A-Za-z0-9]+)~', $p->ref_externa, $m)) $auth = $m[1];

        $webpay = [
            'auth_code'    => $auth,
            'payment_type' => $p->tx_paytype ?? null,
            'installments' => $p->tx_installments ?? null,
            'card_last4'   => $p->tx_last4 ?? null,
        ];

        // (opcional) auditar descarga/visualización del recibo
        AuditoriaService::log('pago', (int)$id, 'RECIBO_VER', ['folio'=>$p->folio ?? null]);

        return response()->view('recibo_html', ['p'=>$p, 'webpay'=>$webpay]);
    }
}
