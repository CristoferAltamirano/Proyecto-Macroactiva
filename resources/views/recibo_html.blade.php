<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recibo #{{ $p->id_pago }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{ font-family:Arial,Helvetica,sans-serif; color:#0f172a; margin:24px; }
    .box{ border:1px solid #e5e7eb; border-radius:12px; padding:16px; max-width:720px; }
    h1{ margin:0 0 8px 0; font-size:22px; }
    .muted{ color:#64748b; }
    table{ width:100%; border-collapse:collapse; margin-top:12px; }
    th,td{ text-align:left; padding:8px 6px; border-bottom:1px solid #e5e7eb; }
    .right{ text-align:right; }
    .total{ font-weight:700; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Recibo de Pago</h1>
    <div class="muted">Documento generado automáticamente por MacroActiva</div>

    <table>
      <tr><th>N° Recibo</th><td>#{{ $p->id_pago }}</td></tr>
      <tr><th>Unidad</th><td>{{ $p->unidad_codigo ?? $p->id_unidad }}</td></tr>
      <tr><th>Fecha de pago</th><td>{{ $p->fecha_pago }}</td></tr>
      <tr><th>Periodo</th><td>{{ $p->periodo ?? '—' }}</td></tr>
      <tr><th>Método</th><td>{{ $p->id_metodo_pago ?? '—' }}</td></tr>
      <tr><th>Referencia</th><td>{{ $p->ref_externa ?? '—' }}</td></tr>
      <tr><th class="total">Monto</th><td class="total right">${{ number_format($p->monto,0,',','.') }}</td></tr>
    </table>

    <p class="muted" style="margin-top:12px">Gracias por su pago.</p>
  </div>
</body>
</html>
