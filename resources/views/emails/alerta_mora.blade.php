<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif">
  <p>Hola {{ $data['nombre'] }},</p>
  <p>Detectamos un <strong>saldo pendiente</strong> para tu unidad <strong>{{ $data['unidad'] }}</strong> del periodo <strong>{{ $data['periodo'] }}</strong>.</p>
  <p>Saldo: <strong>${{ number_format($data['saldo'],0,',','.') }}</strong>. Vencimiento: {{ $data['vencimiento'] ?? '—' }}.</p>
  <p>Por favor, regulariza tu situación a la brevedad.</p>
  <p>Atte.,<br>{{ $data['condominio'] }}</p>
</body></html>
