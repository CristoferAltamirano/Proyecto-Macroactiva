@extends('layout')
@section('title','Pago confirmado')
@section('content')
@include('partials.flash')

<div class="card" style="text-align:center">
  <h3>✅ Pago aprobado</h3>
  <p>Estamos generando tu recibo. La descarga comenzará automáticamente.</p>

  <div style="margin:14px 0">
    <a id="dl" href="{{ $downloadUrl }}" class="btn">Descargar recibo</a>
    <a href="{{ $backUrl }}" class="btn btn--muted">Volver al estado de cuenta</a>
  </div>

  <p class="muted" style="margin-top:8px">
    Si la descarga no comienza, presiona <strong>“Descargar recibo”</strong>.
  </p>
</div>

<script>
(function () {
  try {
    var link = document.getElementById('dl');
    if (link) {
      var iframe = document.createElement('iframe');
      iframe.style.display = 'none';
      iframe.src = link.href; // dispara descarga en segundo plano
      document.body.appendChild(iframe);
    }
  } catch (e) {}

  // Redirige al estado de cuenta tras unos segundos
  setTimeout(function () {
    window.location.href = @json($backUrl);
  }, 2500);
})();
</script>
@endsection
