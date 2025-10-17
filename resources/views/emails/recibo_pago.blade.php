<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif">
  <p>Hola {{ $data['nombre'] }},</p>
  <p>Registramos tu pago para la unidad <strong>{{ $data['unidad'] }}</strong> ({{ $data['periodo'] ?? 'sin periodo' }}) por <strong>${{ number_format($data['monto'],0,',','.') }}</strong> el {{ \Carbon\Carbon::parse($data['fecha'])->format('d/m/Y H:i') }}.</p>
  @if(!empty($data['url_recibo']))
    <p>Descarga el recibo en PDF: <a href="{{ $data['url_recibo'] }}">Recibo</a></p>
  @endif
  <p>¡Gracias!<br>{{ $data['condominio'] }}</p>
</body></html>
