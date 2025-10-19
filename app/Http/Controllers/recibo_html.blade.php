<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recibo pago #{{ $p->id_pago }}</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:24px;color:#0f172a}
    .wrap{max-width:760px;margin:0 auto}
    .head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
    .h1{font-size:20px;font-weight:800;margin:0}
    .muted{color:#64748b}
    .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:12px 0}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width:720px){ .grid{grid-template-columns:1fr} }
    .tag{display:inline-block;background:#0b2a6f;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <div class="h1">Recibo de pago</div>
      <div class="muted">{{ $p->condominio ?? 'Condominio' }} — Unidad {{ $p->unidad ?? $p->id_unidad }}</div>
    </div>
    <div>
      @if(!empty($p->folio))
        <div class="tag">Folio {{ $p->folio }}</div>
      @endif
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <table>
        <tbody>
          <tr><th>ID pago</th><td>#{{ $p->id_pago }}</td></tr>
          <tr><th>Fecha</th><td>{{ \Illuminate\Support\Str::limit((string)$p->fecha_pago,19,'') }}</td></tr>
          <tr><th>Periodo</th><td>{{ $p->periodo ?? '—' }}</td></tr>
          <tr><th>Monto</th><td>${{ number_format($p->monto ?? 0,0,',','.') }}</td></tr>
          <tr><th>Método</th><td>{{ $p->metodo ?? ($p->tipo ?? '—') }}</td></tr>
          @if(!empty($p->ref_externa))
            <tr><th>Referencia</th><td class="muted">{{ $p->ref_externa }}</td></tr>
          @endif
          @if(!empty($p->observacion))
            <tr><th>Observación</th><td class="muted">{{ $p->observacion }}</td></tr>
          @endif
        </tbody>
      </table>
    </div>

    {{-- Bloque Webpay si hay datos --}}
    @php
      $hasWp = !empty($webpay['auth_code']) || !empty($webpay['payment_type']) || !empty($webpay['installments']) || !empty($webpay['card_last4']);
    @endphp
    @if($hasWp)
      <div class="card">
        <h3 style="margin-top:0">Detalle Webpay</h3>
        <table>
          <tbody>
            @if(!empty($webpay['auth_code']))
              <tr><th>Código autorización</th><td>{{ $webpay['auth_code'] }}</td></tr>
            @endif
            @if(!empty($webpay['payment_type']))
              <tr><th>Tipo de pago</th><td>{{ $webpay['payment_type'] }}</td></tr>
            @endif
            @if(!empty($webpay['installments']))
              <tr><th>N° de cuotas</th><td>{{ $webpay['installments'] }}</td></tr>
            @endif
            @if(!empty($webpay['card_last4']))
              <tr><th>Tarjeta</th><td>**** **** **** {{ $webpay['card_last4'] }}</td></tr>
            @endif
          </tbody>
        </table>
        <p class="muted" style="margin:8px 0 0">* Información reportada por Transbank.</p>
      </div>
    @endif
  </div>

  <p class="muted" style="margin-top:14px">Documento generado por el sistema. Si requiere timbraje/QR, puede emitirse un PDF firmado desde <em>Comprobantes</em>.</p>
</div>
</body>
</html>
