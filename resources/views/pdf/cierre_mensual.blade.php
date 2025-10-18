<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{ font-family: DejaVu Sans, sans-serif; font-size:12px; }
h1{ font-size:18px; margin:0 0 8px 0; }
h2{ font-size:14px; margin:12px 0 6px 0; }
table{ width:100%; border-collapse:collapse; }
th,td{ border:1px solid #ddd; padding:6px; }
.right{ text-align:right; }
.muted{ color:#666; font-size:11px; }
</style></head><body>
<h1>Cierre mensual - {{ $condo->nombre }} ({{ $periodo }})</h1>
<p class="muted">{{ $condo->direccion ?? '' }} | {{ $condo->email_contacto ?? '' }}</p>

<h2>Resumen</h2>
<table>
  <tbody>
    <tr><td>Total cargos del periodo</td><td class="right">${{ number_format($res->total_cargos,0,',','.') }}</td></tr>
    <tr><td>Interés generado</td><td class="right">${{ number_format($res->total_interes,0,',','.') }}</td></tr>
    <tr><td>Descuentos</td><td class="right">- ${{ number_format($res->total_descuentos,0,',','.') }}</td></tr>
    <tr><td>Total pagado</td><td class="right">- ${{ number_format($res->total_pagado,0,',','.') }}</td></tr>
    <tr><td><strong>Saldo por cobrar (periodo)</strong></td><td class="right"><strong>${{ number_format($res->saldo_por_cobrar,0,',','.') }}</strong></td></tr>
    <tr><td>Gastos (SII)</td><td class="right">${{ number_format($res->total_gastos,0,',','.') }}</td></tr>
  </tbody>
</table>

<h2>Fondo de reserva (movimientos del periodo)</h2>
<table>
  <thead><tr><th>Fecha</th><th>Tipo</th><th>Glosa</th><th class="right">Monto</th></tr></thead>
  <tbody>
    @php $frTotal=0; @endphp
    @forelse($fr as $m)
      @php $sign = $m->tipo==='abono' ? 1 : -1; $frTotal += $sign*(float)$m->monto; @endphp
      <tr><td>{{ $m->fecha }}</td><td>{{ $m->tipo }}</td><td>{{ $m->glosa }}</td><td class="right">{{ number_format($sign*$m->monto,0,',','.') }}</td></tr>
    @empty
      <tr><td colspan="4" class="muted">Sin movimientos.</td></tr>
    @endforelse
    <tr><td colspan="3" class="right"><strong>Total periodo</strong></td><td class="right"><strong>{{ number_format($frTotal,0,',','.') }}</strong></td></tr>
  </tbody>
</table>

<h2>Top deudores del periodo</h2>
<table>
  <thead><tr><th>Unidad</th><th class="right">Saldo</th></tr></thead>
  <tbody>
    @forelse($deudores as $d)
    <tr><td>{{ $d->unidad }}</td><td class="right">${{ number_format($d->saldo,0,',','.') }}</td></tr>
    @empty
    <tr><td colspan="2" class="muted">Sin saldos pendientes.</td></tr>
    @endforelse
  </tbody>
</table>

<p class="muted">Documento generado: {{ now() }}</p>
</body></html>
