<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{ font-family: DejaVu Sans, sans-serif; font-size:12px; }
h1{ font-size:18px; margin:0 0 4px 0; }
table{ width:100%; border-collapse:collapse; }
th,td{ border:1px solid #ddd; padding:6px; }
.section{ margin:14px 0; }
.right{ text-align:right }
</style></head><body>
<h1>Aviso de Cobro</h1>
<p><strong>Periodo:</strong> {{ $c->periodo }} &nbsp; | &nbsp; <strong>Unidad:</strong> {{ $c->unidad }} ({{ $c->grupo }}) &nbsp; | &nbsp; <strong>Estado:</strong> {{ $c->estado }}</p>

<div class="section">
  <table>
    <thead><tr><th>Tipo</th><th>Glosa</th><th class="right">Monto</th></tr></thead>
    <tbody>
      @php $bruto=0; @endphp
      @foreach($det as $d)
        @php $sign = in_array($d->tipo,['descuento']) ? -1 : 1; $m = $sign * (float)$d->monto; $bruto += $m; @endphp
        <tr><td>{{ $d->tipo }}</td><td>{{ $d->glosa }}</td><td class="right">{{ number_format($m,0,',','.') }}</td></tr>
      @endforeach
      <tr><td colspan="2" class="right"><strong>Total cargos + interés - descuentos</strong></td><td class="right"><strong>{{ number_format($bruto,0,',','.') }}</strong></td></tr>
    </tbody>
  </table>
</div>

<div class="section">
  <table>
    <thead><tr><th>Fecha pago</th><th class="right">Monto aplicado</th></tr></thead>
    <tbody>
      @php $aplic=0; @endphp
      @foreach($pagos as $p) @php $aplic += (float)$p->monto_aplicado; @endphp
        <tr><td>{{ $p->fecha_pago }}</td><td class="right">{{ number_format($p->monto_aplicado,0,',','.') }}</td></tr>
      @endforeach
      <tr><td class="right"><strong>Total pagado</strong></td><td class="right"><strong>{{ number_format($aplic,0,',','.') }}</strong></td></tr>
      <tr><td class="right"><strong>Saldo</strong></td><td class="right"><strong>{{ number_format($c->saldo,0,',','.') }}</strong></td></tr>
    </tbody>
  </table>
</div>

<p style="font-size:11px">* Este aviso incluye cargos comunes, cargos individuales, intereses por mora y descuentos conforme al reglamento del condominio.</p>
</body></html>
