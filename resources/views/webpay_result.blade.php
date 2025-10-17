@extends('layout')
@section('title','Resultado Webpay')
@section('content')
@include('partials.flash')
<div class="card">
  <h3>{{ $ok ? 'Pago aprobado' : 'Pago rechazado' }}</h3>
  <p>{{ $message }}</p>
  @if(isset($resp))
    <div class="muted" style="font-size:14px">
      Orden: {{ $resp->getBuyOrder() ?? '—' }}<br>
      Autorización: {{ method_exists($resp,'getAuthorizationCode') ? ($resp->getAuthorizationCode() ?? '—') : '—' }}<br>
      Monto: ${{ number_format($resp->getAmount() ?? 0, 0, ',', '.') }}
    </div>
  @endif
  <p style="margin-top:12px">
    <a class="btn" href="{{ route('estado.cuenta') }}">Volver a mi estado de cuenta</a>
  </p>
</div>
@endsection
