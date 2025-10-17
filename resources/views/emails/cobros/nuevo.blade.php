<x-mail::message>
# ¡Nuevo Cobro Generado!

Hola, Residente de la Unidad **{{ $cobro->unidad->numero }}**.

Te informamos que se ha generado un nuevo cobro de gastos comunes para el periodo de **{{ $cobro->periodo->translatedFormat('F Y') }}**.

El monto total de este periodo es de **${{ number_format($cobro->monto_total, 0, ',', '.') }}**.

Puedes ver el detalle completo y realizar el pago directamente en nuestro portal de residentes.

<x-mail::button :url="route('portal.dashboard')">
Ver mi Estado de Cuenta
</x-mail::button>

Gracias,<br>
La Administración de {{ config('app.name') }}
</x-mail::message>