<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Redirigiendo a Webpay…</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body onload="document.getElementById('wp').submit()">
  <p>Redirigiendo a Webpay…</p>
  <form id="wp" method="POST" action="{{ $url }}">
    <input type="hidden" name="token_ws" value="{{ $token }}">
    <noscript><button>Continuar</button></noscript>
  </form>
</body>
</html>
