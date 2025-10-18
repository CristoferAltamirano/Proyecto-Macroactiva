<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif">
  <p>Hola {{ $data['nombre'] }},</p>
  <p>Se ha emitido el <strong>aviso de cobro</strong> de Gastos Comunes para la unidad <strong>{{ $data['unidad'] }}</strong> correspondiente al periodo <strong>{{ $data['periodo'] }}</strong>.</p>
  <p>Monto total a pagar: <strong>${{ number_format($data['monto'],0,',','.') }}</strong></p>
  @if(!empty($data['url_pdf']))
    <p>Puedes descargar el PDF aquí: <a href="{{ $data['url_pdf'] }}">Aviso de Cobro</a></p>
  @endif
  <p>Atte.,<br>{{ $data['condominio'] }}</p>
</body></html>
