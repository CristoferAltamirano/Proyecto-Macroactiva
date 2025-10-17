@extends('portal.layout')

@section('title', 'Detalle del Cobro - ' . \Carbon\Carbon::parse($cobro->periodo)->translatedFormat('F Y'))

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Detalle del Cobro</h1>
        <a href="{{ route('portal.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Resumen del Periodo</h5>
                    <p class="mb-0 text-muted">{{ \Carbon\Carbon::parse($cobro->periodo)->translatedFormat('F Y') }}</p>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Gasto Común
                            <span>${{ number_format($cobro->monto_gasto_comun, 0, ',', '.') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Fondo de Reserva
                            <span>${{ number_format($cobro->monto_fondo_reserva, 0, ',', '.') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Multas e Intereses
                            <span>${{ number_format($cobro->monto_multas, 0, ',', '.') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold fs-5">
                            Total a Pagar
                            <span>${{ number_format($cobro->monto_total, 0, ',', '.') }}</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer text-center">
                    @if($cobro->estado === 'pendiente')
                        <button class="btn btn-success btn-lg w-100">
                            <i class="bi bi-credit-card-fill me-2"></i> Pagar con Webpay
                        </button>
                    @else
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle-fill me-2"></i> Este cobro ya fue pagado.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Desglose de Gastos Comunes del Periodo</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($gastosDelPeriodo as $gasto)
                                <tr>
                                    <td>{{ $gasto->descripcion }}</td>
                                    <td class="text-end">${{ number_format($gasto->monto, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No hay gastos detallados para este periodo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total Gastos del Periodo</td>
                                <td class="text-end">${{ number_format($gastosDelPeriodo->sum('monto'), 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection