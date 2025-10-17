<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante {{ $comp->folio }}</title>
<style>
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#0f172a; font-size:12px; }
  .wrap{ max-width:760px; margin:0 auto; }
  h1{ font-size:18px; margin:0 0 6px 0; }
  .muted{ color:#64748b; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ padding:6px; border-bottom:1px solid #e5e7eb; text-align:left; }
  .right{ text-align:right }
  .box{ border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-top:10px; }
  .row{ display:flex; gap:12px; }
  .col{ flex:1; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Comprobante de Pago</h1>
  <div class="muted">Folio: <strong>{{ $comp->folio }}</strong> — Emitido: {{ $comp->emitido_at }}</div>

  <div class="box">
    <table>
      <tr><th>ID Pago</th><td>#{{ $comp->id_pago }}</td></tr>
      <tr><th>Condominio</th><td>{{ $p->condominio ?? '—' }}</td></tr>
      <tr><th>Unidad</th><td>{{ $p->unidad_codigo ?? $p->id_unidad }}</td></tr>
      <tr><th>Fecha de pago</th><td>{{ $p->fecha_pago }}</td></tr>
      <tr><th>Periodo</th><td>{{ $p->periodo ?? '—' }}</td></tr>
      <tr><th>Método</th><td>{{ $p->metodo ?? '—' }}</td></tr>
      <tr><th>Referencia</th><td>{{ $p->ref_externa ?? '—' }}</td></tr>
      <tr><th class="right">Monto</th><td class="right"><strong>${{ number_format($p->monto,0,',','.') }}</strong></td></tr>
    </table>
  </div>

  <div class="row">
    <div class="col box">
      <strong>Firma digital (HMAC):</strong>
      <div style="word-break:break-all">{{ $sig }}</div>
      <div class="muted" style="margin-top:6px">Para validar este comprobante, escanee el QR o visite la URL de verificación.</div>
    </div>
    <div class="col box" style="text-align:center">
      <div class="muted">Verificación</div>
      {{-- QR online (MVP); si no hay internet, el link de texto sigue funcionando --}}
      <img alt="QR" style="margin-top:6px"
           src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode($verifyUrl) }}">
      <div style="font-size:10px; margin-top:6px; word-break:break-all">{{ $verifyUrl }}</div>
    </div>
  </div>

  <p class="muted" style="margin-top:10px">Documento generado por MacroActiva. La “firma” es un sello HMAC basado en APP_KEY.</p>
</div>
</body>
</html>
