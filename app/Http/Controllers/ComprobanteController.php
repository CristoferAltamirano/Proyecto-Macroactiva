<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Si tienes barryvdh/laravel-dompdf
use Barryvdh\DomPDF\Facade\Pdf;

class ComprobanteController extends Controller
{
    /**
     * Genera el PDF del comprobante firmado.
     * Ruta: GET /comprobantes/{id}.pdf
     */
    public function pdf(int $id)
    {
        $c = DB::table('comprobante_pago as cp')
            ->join('pago as p','p.id_pago','=','cp.id_pago')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as mp','mp.id_metodo_pago','=','p.id_metodo_pago')
            ->selectRaw("
                cp.id_compr_pago, cp.folio, cp.emitido_at,
                p.id_pago, p.fecha_pago, p.periodo, p.monto, p.id_metodo_pago, p.ref_externa, p.observacion,
                u.codigo as unidad_codigo,
                c.nombre as condominio_nombre,
                mp.nombre as metodo_nombre
            ")
            ->where('cp.id_compr_pago',$id)
            ->first();

        if (!$c) abort(404, 'Comprobante no encontrado');

        // Firma HMAC (estable: usa folio|id_pago|monto(2dec)|fecha_emision)
        $sig = $this->sign($c->folio, (int)$c->id_pago, (float)$c->monto, substr($c->emitido_at,0,10));
        $verifyUrl = route('comprobante.verify', ['folio'=>$c->folio,'sig'=>$sig], true);

        $html = $this->renderHtml($c, $sig, $verifyUrl);

        // Si DomPDF está disponible, generamos PDF. Si no, devolvemos HTML.
        try {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $pdf = Pdf::loadHTML($html)->setPaper('A4','portrait');
                return $pdf->stream('comprobante-'.$c->folio.'.pdf');
            }
        } catch (\Throwable $e) {
            // Si hay error con PDF, caemos a HTML
        }

        return response($html, 200, ['Content-Type'=>'text/html; charset=UTF-8']);
    }

    /**
     * Verifica la validez del comprobante por HMAC.
     * Ruta: GET /comprobantes/verify?folio=...&sig=...
     */
    public function verify(Request $r)
    {
        $folio = trim((string)$r->query('folio',''));
        $sig   = trim((string)$r->query('sig',''));

        if ($folio === '' || $sig === '') {
            return response($this->renderVerifyPage(null, false, 'Parámetros incompletos.'), 200);
        }

        $c = DB::table('comprobante_pago as cp')
            ->join('pago as p','p.id_pago','=','cp.id_pago')
            ->join('unidad as u','u.id_unidad','=','p.id_unidad')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
            ->leftJoin('condominio as co','co.id_condominio','=','g.id_condominio')
            ->leftJoin('cat_metodo_pago as mp','mp.id_metodo_pago','=','p.id_metodo_pago')
            ->selectRaw("
                cp.id_compr_pago, cp.folio, cp.emitido_at,
                p.id_pago, p.fecha_pago, p.periodo, p.monto, p.ref_externa,
                u.codigo as unidad_codigo,
                co.nombre as condominio_nombre,
                mp.nombre as metodo_nombre
            ")
            ->where('cp.folio',$folio)
            ->first();

        if (!$c) {
            return response($this->renderVerifyPage(null, false, 'Folio no encontrado.'), 404);
        }

        $expected = $this->sign($c->folio, (int)$c->id_pago, (float)$c->monto, substr($c->emitido_at,0,10));
        $ok = hash_equals($expected, $sig);

        return response($this->renderVerifyPage($c, $ok, $ok ? null : 'Firma inválida o alteración detectada.'), $ok ? 200 : 400);
    }

    /* ================= Helpers internos ================= */

    /**
     * Devuelve la clave HMAC desde .env
     */
    private function key(): string
    {
        return (string) env('COMPROBANTE_KEY', 'cambia-esta-clave');
    }

    /**
     * Crea la cadena base y firma HMAC-SHA256
     */
    private function sign(string $folio, int $idPago, float $monto, string $fechaEmision): string
    {
        $base = $folio.'|'.$idPago.'|'.number_format($monto,2,'.','').'|'.$fechaEmision;
        return hash_hmac('sha256', $base, $this->key());
    }

    /**
     * HTML del comprobante (para PDF/HTML)
     */
    private function renderHtml(object $c, string $sig, string $verifyUrl): string
    {
        $css = <<<CSS
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;margin:24px;color:#0f172a}
            .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #e5e7eb;padding-bottom:8px;margin-bottom:12px}
            .brand{font-weight:800;color:#0b2a6f;font-size:20px}
            .folio{font-weight:700}
            .card{border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:12px}
            .muted{color:#64748b}
            table{width:100%;border-collapse:collapse}
            th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
            .right{text-align:right}
            .verify{font-size:12px;margin-top:12px}
            .sig{font-family:monospace;font-size:12px;word-break:break-all}
        CSS;

        $montoFmt = '$'.number_format((float)$c->monto,0,',','.');
        $fechaPago = substr((string)$c->fecha_pago,0,19);
        $fechaEmi  = substr((string)$c->emitido_at,0,19);
        $condo = $c->condominio_nombre ?: '—';
        $unidad = $c->unidad_codigo ?: '—';
        $metodo = $c->metodo_nombre ?: '—';
        $periodo = $c->periodo ?: '—';
        $ref = $c->ref_externa ?: '—';

        return <<<HTML
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Comprobante {$c->folio}</title>
<style>{$css}</style>
</head><body>
  <div class="head">
    <div>
      <div class="brand">MacroActiva</div>
      <div class="muted">Comprobante de pago</div>
    </div>
    <div style="text-align:right">
      <div class="folio">Folio: {$c->folio}</div>
      <div class="muted">Emitido: {$fechaEmi}</div>
    </div>
  </div>

  <div class="card">
    <table>
      <tr><th>Condominio</th><td>{$condo}</td><th>Unidad</th><td>{$unidad}</td></tr>
      <tr><th>ID Pago</th><td>{$c->id_pago}</td><th>Periodo</th><td>{$periodo}</td></tr>
      <tr><th>Fecha de pago</th><td>{$fechaPago}</td><th>Método</th><td>{$metodo}</td></tr>
      <tr><th>Referencia</th><td>{$ref}</td><th>Monto</th><td class="right"><strong>{$montoFmt}</strong></td></tr>
    </table>
  </div>

  <div class="card">
    <div><strong>Validación en línea</strong></div>
    <div class="verify">
      Para verificar la autenticidad de este comprobante, visite:<br>
      <a href="{$verifyUrl}">{$verifyUrl}</a>
    </div>
    <div class="verify">Firma (HMAC-SHA256):</div>
    <div class="sig">{$sig}</div>
  </div>

  <div class="muted" style="margin-top:8px">Este documento ha sido firmado digitalmente por el emisor. 
  La validez depende de la coincidencia exacta del folio, pago y monto con la firma.</div>
</body></html>
HTML;
    }

    /**
     * HTML para la página de verificación
     */
    private function renderVerifyPage(?object $c, bool $ok, ?string $msg): string
    {
        $css = <<<CSS
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;margin:24px;color:#0f172a}
            .box{max-width:680px;margin:0 auto}
            .title{font-weight:800;color:#0b2a6f;font-size:22px}
            .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:10px;border-radius:10px}
            .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:10px;border-radius:10px}
            table{width:100%;border-collapse:collapse;margin-top:12px}
            th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
            .muted{color:#64748b}
            .hint{font-size:13px;color:#64748b;margin-top:10px}
        CSS;

        $status = $ok ? '<div class="ok"><strong>VÁLIDO:</strong> La firma coincide con el comprobante.</div>'
                      : '<div class="err"><strong>INVÁLIDO:</strong> '.e($msg ?? 'No válido').'</div>';

        $det = '';
        if ($c) {
            $montoFmt = '$'.number_format((float)$c->monto,0,',','.');
            $fechaEmi = substr((string)$c->emitido_at,0,19);
            $fechaPago = substr((string)$c->fecha_pago,0,19);
            $condo = $c->condominio_nombre ?: '—';
            $unidad = $c->unidad_codigo ?: '—';
            $metodo = $c->metodo_nombre ?: '—';
            $periodo = $c->periodo ?: '—';
            $ref = $c->ref_externa ?: '—';

            $det = <<<HTML
<table>
  <tr><th>Folio</th><td>{$c->folio}</td><th>ID Pago</th><td>{$c->id_pago}</td></tr>
  <tr><th>Condominio</th><td>{$condo}</td><th>Unidad</th><td>{$unidad}</td></tr>
  <tr><th>Periodo</th><td>{$periodo}</td><th>Método</th><td>{$metodo}</td></tr>
  <tr><th>Monto</th><td>{$montoFmt}</td><th>Fecha pago</th><td>{$fechaPago}</td></tr>
  <tr><th>Emitido</th><td>{$fechaEmi}</td><th>Referencia</th><td>{$ref}</td></tr>
</table>
HTML;
        } else {
            $det = '<div class="hint">Indique parámetros <code>?folio=F0001&amp;sig=...</code> para verificar un comprobante.</div>';
        }

        return <<<HTML
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Verificación de Comprobante</title>
<style>{$css}</style>
</head><body>
  <div class="box">
    <div class="title">MacroActiva — Verificación de Comprobante</div>
    <div style="height:8px"></div>
    {$status}
    <div style="height:12px"></div>
    {$det}
    <div class="hint">Esta verificación utiliza firma HMAC-SHA256 con clave privada del emisor.</div>
  </div>
</body></html>
HTML;
    }
}
