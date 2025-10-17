<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditoriaService;

class WebpayController extends Controller
{
    /** ====== CONFIG COMÚN (Integración) ====== */
    private const INT_BASE = 'https://webpay3gint.transbank.cl/rswebpaytransaction/api/webpay/v1.2';
    private const INT_CODE = '597055555532'; // Webpay Plus integración
    private const INT_KEY  = 'test';

    /** Genera buyOrder <= 26 chars */
    private function makeBuyOrder(int $idUnidad): string
    {
        // U{unidad}T{yymmddHHMMSS} -> p.ej U12T250923205945
        return 'U'.$idUnidad.'T'.now()->format('ymdHis');
    }

    /** ====== START: inicia transacción ====== */
    public function start(Request $r)
    {
        $data = $r->validate([
            'id_unidad' => ['required','integer','min:1'],
            'monto'     => ['required','numeric','min:1'],
            'periodo'   => ['nullable','regex:/^[0-9]{6}$/'],
        ]);

        $idUnidad = (int)$data['id_unidad'];
        $amount   = (int) round((float)$data['monto'], 0);
        $periodo  = $data['periodo'] ?? null;

        $buyOrder  = $this->makeBuyOrder($idUnidad);
        $sessionId = 'S-'.($r->user()->id_usuario ?? $r->user()->id ?? 0).'-'.now()->timestamp;
        $returnUrl = route('webpay.return');

        try {
            // 1) Si existe SDK Transaction, úsalo
            if (class_exists('\Transbank\Webpay\WebpayPlus\Transaction')) {
                // Intento configurar integración (distintas versiones del SDK)
                if (class_exists('\Transbank\Webpay\WebpayPlus\WebpayPlus')) {
                    try { \Transbank\Webpay\WebpayPlus\WebpayPlus::configureForIntegration(); } catch (\Throwable $e) {}
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction', 'setCommerceCode')) {
                    \Transbank\Webpay\WebpayPlus\Transaction::setCommerceCode(self::INT_CODE);
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction', 'setApiKey')) {
                    \Transbank\Webpay\WebpayPlus\Transaction::setApiKey(self::INT_KEY);
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction', 'setIntegrationType')) {
                    try { \Transbank\Webpay\WebpayPlus\Transaction::setIntegrationType('TEST'); } catch (\Throwable $e) {}
                }

                $tx   = new \Transbank\Webpay\WebpayPlus\Transaction();
                $resp = $tx->create($buyOrder, $sessionId, $amount, $returnUrl);

                $token = method_exists($resp,'getToken') ? $resp->getToken() : ($resp->token ?? null);
                $url   = method_exists($resp,'getUrl')   ? $resp->getUrl()   : ($resp->url   ?? null);

                if ($token && $url) {
                    session([
                        'wp.id_unidad' => $idUnidad,
                        'wp.periodo'   => $periodo,
                        'wp.monto'     => $amount,
                        'wp.buyOrder'  => $buyOrder,
                    ]);

                    // Auditoría: inicio de webpay
                    AuditoriaService::log('pago', 0, 'WEBPAY_START', [
                        'id_unidad' => $idUnidad,
                        'periodo'   => $periodo,
                        'monto'     => $amount,
                        'buyOrder'  => $buyOrder,
                        'token'     => $token,
                    ]);

                    return response()->view('webpay_redirect', compact('url','token'));
                }

                Log::warning('WEBPAY START SDK sin token/url', ['resp'=>$resp]);
                // Continúa con fallback HTTP…
            }

            // 2) Fallback HTTP puro (integración)
            $res = Http::withHeaders([
                'Tbk-Api-Key-Id'     => self::INT_CODE,
                'Tbk-Api-Key-Secret' => self::INT_KEY,
                'Content-Type'       => 'application/json',
                'Accept'             => 'application/json',
            ])->timeout(15)->post(self::INT_BASE.'/transactions', [
                'buy_order'  => $buyOrder,
                'session_id' => $sessionId,
                'amount'     => $amount,
                'return_url' => $returnUrl,
            ]);

            if (!$res->ok()) {
                Log::error('WEBPAY START HTTP ERROR', [
                    'status'  => $res->status(),
                    'body'    => $res->body(),
                ]);
                AuditoriaService::log('pago', 0, 'WEBPAY_START_ERROR', [
                    'id_unidad'=>$idUnidad,'periodo'=>$periodo,'monto'=>$amount,'buyOrder'=>$buyOrder,
                    'http_status'=>$res->status()
                ]);
                return back()->with('err','No se pudo iniciar el pago (HTTP).');
            }

            $json  = $res->json();
            $token = $json['token'] ?? null;
            $url   = $json['url']   ?? null;

            if (!$token || !$url) {
                Log::error('WEBPAY START HTTP sin token/url', ['json'=>$json]);
                AuditoriaService::log('pago', 0, 'WEBPAY_START_ERROR', [
                    'id_unidad'=>$idUnidad,'periodo'=>$periodo,'monto'=>$amount,'buyOrder'=>$buyOrder,
                    'detalle'=>'sin_token_url'
                ]);
                return back()->with('err','No se obtuvo token/URL desde Webpay (HTTP).');
            }

            session([
                'wp.id_unidad' => $idUnidad,
                'wp.periodo'   => $periodo,
                'wp.monto'     => $amount,
                'wp.buyOrder'  => $buyOrder,
            ]);

            AuditoriaService::log('pago', 0, 'WEBPAY_START', [
                'id_unidad' => $idUnidad,
                'periodo'   => $periodo,
                'monto'     => $amount,
                'buyOrder'  => $buyOrder,
                'token'     => $token,
            ]);

            return response()->view('webpay_redirect', compact('url','token'));
        } catch (\Throwable $e) {
            Log::error('WEBPAY START ERROR', ['e' => $e->getMessage()]);
            AuditoriaService::log('pago', 0, 'WEBPAY_START_ERROR', [
                'id_unidad'=>$idUnidad,'periodo'=>$periodo,'monto'=>$amount,'buyOrder'=>$buyOrder,
                'msg'=>$e->getMessage()
            ]);
            return back()->with('err', 'No se pudo iniciar el pago: '.$e->getMessage());
        }
    }

    /** ====== RETURN: confirmación (commit) ====== */
    public function return(Request $r)
    {
        $token = $r->input('token_ws') ?? $r->query('token_ws');
        if (!$token) {
            AuditoriaService::log('pago', 0, 'WEBPAY_CANCEL', []);
            return redirect()->route('estado.cuenta')->with('err', 'Transacción cancelada.');
        }

        try {
            $approved = false;
            $authCode = null;
            $status   = null;

            // 1) Commit con SDK si existe
            if (class_exists('\Transbank\Webpay\WebpayPlus\Transaction')) {
                if (class_exists('\Transbank\Webpay\WebpayPlus\WebpayPlus')) {
                    try { \Transbank\Webpay\WebpayPlus\WebpayPlus::configureForIntegration(); } catch (\Throwable $e) {}
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction','setCommerceCode')) {
                    \Transbank\Webpay\WebpayPlus\Transaction::setCommerceCode(self::INT_CODE);
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction','setApiKey')) {
                    \Transbank\Webpay\WebpayPlus\Transaction::setApiKey(self::INT_KEY);
                }
                if (method_exists('\Transbank\Webpay\WebpayPlus\Transaction','setIntegrationType')) {
                    try { \Transbank\Webpay\WebpayPlus\Transaction::setIntegrationType('TEST'); } catch (\Throwable $e) {}
                }

                $resp = (new \Transbank\Webpay\WebpayPlus\Transaction())->commit($token);

                // Aprobación (SDK moderno o compat)
                if (method_exists($resp, 'isApproved') && $resp->isApproved()) {
                    $approved = true;
                }
                if (method_exists($resp, 'getResponseCode') && $resp->getResponseCode() === 0) {
                    $approved = true;
                } elseif (property_exists($resp, 'response_code') && $resp->response_code === 0) {
                    $approved = true;
                }

                $authCode = method_exists($resp,'getAuthorizationCode') ? $resp->getAuthorizationCode() : ($resp->authorization_code ?? null);
                $status   = method_exists($resp,'getStatus')             ? $resp->getStatus()             : ($resp->status ?? null);
            }

            // 2) Fallback HTTP si aún no aprobado
            if (!$approved) {
                $res = Http::withHeaders([
                    'Tbk-Api-Key-Id'     => self::INT_CODE,
                    'Tbk-Api-Key-Secret' => self::INT_KEY,
                    'Content-Type'       => 'application/json',
                    'Accept'             => 'application/json',
                ])->timeout(15)->put(self::INT_BASE.'/transactions/'.$token, []);

                if (!$res->ok()) {
                    Log::error('WEBPAY RETURN HTTP ERROR', [
                        'status' => $res->status(),
                        'body'   => $res->body(),
                    ]);
                } else {
                    $json     = $res->json();
                    $status   = $json['status'] ?? null;
                    $authCode = $json['authorization_code'] ?? null;
                    $approved = ($status === 'AUTHORIZED') || ((int)($json['response_code'] ?? 99) === 0);
                }
            }

            if (!$approved) {
                AuditoriaService::log('pago', 0, 'WEBPAY_RECHAZADO', [
                    'token'=>$token,
                    'status'=>$status,
                    'buyOrder'=>session('wp.buyOrder'),
                    'id_unidad'=>session('wp.id_unidad'),
                    'periodo'=>session('wp.periodo'),
                    'monto'=>session('wp.monto'),
                ]);
                return redirect()->route('estado.cuenta')->with('err', 'Pago rechazado: '.($status ?: 'DESCONOCIDO'));
            }

            // ===== Registro del pago en BD =====
            $idUnidad = (int) session('wp.id_unidad');
            $periodo  = session('wp.periodo');
            $monto    = (float) session('wp.monto');
            $buyOrder = session('wp.buyOrder');
            session()->forget(['wp.id_unidad','wp.periodo','wp.monto','wp.buyOrder']);

            // Armar insert respetando columnas/tipos existentes
            $insert = [];
            if (Schema::hasColumn('pago','id_unidad'))       $insert['id_unidad'] = $idUnidad;
            if (Schema::hasColumn('pago','fecha_pago'))      $insert['fecha_pago'] = now();
            if (Schema::hasColumn('pago','periodo'))         $insert['periodo']    = $periodo;
            if (Schema::hasColumn('pago','monto'))           $insert['monto']      = $monto;
            if (Schema::hasColumn('pago','id_metodo_pago')) {
                $idMetodo = DB::table('cat_metodo_pago')->where('codigo','webpay')->value('id_metodo_pago');
                $insert['id_metodo_pago'] = $idMetodo ?: null;
            }
            if (Schema::hasColumn('pago','ref_externa'))     $insert['ref_externa'] = 'TBK:'.$buyOrder.';auth:'.$authCode;
            if (Schema::hasColumn('pago','observacion'))     $insert['observacion'] = 'Pago Webpay (token '.$token.')';

            // Manejo robusto de 'tipo' (string vs numérica)
            $tipoCol = Schema::hasColumn('pago','tipo');
            $tipoSet = false;
            if ($tipoCol) {
                try {
                    $colType = Schema::getColumnType('pago','tipo'); // 'string','integer', etc.
                } catch (\Throwable $e) {
                    $colType = 'string'; // fallback
                }
                if (in_array($colType, ['string','text'])) {
                    $insert['tipo'] = 'webpay';
                    $tipoSet = true;
                } elseif (in_array($colType, ['integer','bigint','smallint','tinyint','boolean'])) {
                    // Usamos 2 para "webpay" (convención), 1 sería "normal"
                    $insert['tipo'] = 2;
                    $tipoSet = true;
                }
            }

            // Intento 1: insertar tal cual
            try {
                $idPago = DB::table('pago')->insertGetId($insert);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                // Si el problema es el campo 'tipo', reintentar con opciones seguras
                if ($tipoCol && stripos($msg, "column 'tipo'") !== false) {
                    try {
                        // 1) Si era string, probar con 'normal'; si era numérico, probar con 1
                        if ($tipoSet) {
                            if (isset($insert['tipo']) && $insert['tipo'] === 'webpay') {
                                $insert['tipo'] = 'normal';
                            } elseif (isset($insert['tipo']) && (int)$insert['tipo'] === 2) {
                                $insert['tipo'] = 1;
                            }
                        } else {
                            // 2) Omitir 'tipo' si no pudimos inferir nada
                            unset($insert['tipo']);
                        }
                        $idPago = DB::table('pago')->insertGetId($insert);
                    } catch (\Throwable $e2) {
                        Log::error('WEBPAY RETURN INSERT ERROR (retry)', ['e'=>$e2->getMessage()]);
                        AuditoriaService::log('pago', 0, 'WEBPAY_PAGO_INSERT_ERROR', ['msg'=>$e2->getMessage()]);
                        return redirect()->route('estado.cuenta')->with('err','Pago aprobado, pero no se pudo registrar (tipo).');
                    }
                } else {
                    Log::error('WEBPAY RETURN INSERT ERROR', ['e'=>$msg]);
                    AuditoriaService::log('pago', 0, 'WEBPAY_PAGO_INSERT_ERROR', ['msg'=>$msg]);
                    return redirect()->route('estado.cuenta')->with('err','Pago aprobado, pero no se pudo registrar.');
                }
            }

            // Auditoría clave solicitada: pago aprobado por Webpay
            AuditoriaService::log('pago', $idPago, 'WEBPAY_OK', [
                'buyOrder' => $buyOrder ?? null,
                'auth'     => $authCode ?? null,
                'status'   => $status ?? null,
                'token'    => $token,
            ]);

            // Auditoría: creación del pago vía Webpay
            AuditoriaService::log('pago', $idPago, 'CREAR', $insert);

            // Comprobante (si aplica)
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
                    AuditoriaService::log('pago', $idPago, 'COMPROBANTE_CREAR', ['folio'=>$folio, 'via'=>'webpay']);
                }
            }

            // Aplicación FIFO a cobros + RE-CÁLCULO INMEDIATO DEL SALDO
            if (Schema::hasTable('cobro') && Schema::hasTable('pago_aplicacion')) {
                $restante = $monto;
                $cobros = DB::table('cobro')
                    ->where('id_unidad',$idUnidad)
                    ->where('saldo','>',0)
                    ->orderBy('periodo')
                    ->get();

                $aplics = [];
                foreach ($cobros as $c) {
                    if ($restante <= 0) break;
                    $aplicar = min($restante, (float)$c->saldo);

                    DB::table('pago_aplicacion')->insert([
                        'id_pago'        => $idPago,
                        'id_cobro'       => $c->id_cobro,
                        'monto_aplicado' => $aplicar,
                    ]);
                    $aplics[] = ['id_cobro'=>$c->id_cobro, 'periodo'=>$c->periodo, 'monto'=>$aplicar];

                    // Recalcular totales/estado de ese cobro para que el saldo baje al tiro
                    try {
                        \App\Services\CobroService::recalcularTotales((int)$c->id_cobro);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo recalcular cobro', [
                            'id_cobro' => $c->id_cobro,
                            'e'        => $e->getMessage(),
                        ]);
                    }

                    $restante = round($restante - $aplicar, 2);
                }

                if (!empty($aplics)) {
                    AuditoriaService::log('pago', $idPago, 'APLICACION_COBROS', ['detalles'=>$aplics]);
                }
            }

            // 👉 Página puente: descarga PDF en segundo plano y vuelve al estado de cuenta
            $downloadUrl = route('pagos.recibo.pdf', ['id' => $idPago, 'download' => 1]);
            $backUrl     = route('estado.cuenta');

            return response()->view('webpay_ok', compact('downloadUrl','backUrl','idPago'));

        } catch (\Throwable $e) {
            Log::error('WEBPAY RETURN ERROR', ['e' => $e->getMessage()]);
            AuditoriaService::log('pago', 0, 'WEBPAY_RETURN_ERROR', ['msg'=>$e->getMessage(),'token'=>$token]);
            return redirect()->route('estado.cuenta')->with('err','Error al confirmar pago: '.$e->getMessage());
        }
    }

    /** Notificación server-to-server (opcional) */
    public function notify(Request $r)
    {
        return response('OK');
    }
}
