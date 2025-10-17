@extends('portal.layout')

@section('title', 'Mi Dashboard')

@section('content')
<div class="container">
    <h1 class="mb-3">Bienvenido, Residente de la Unidad {{ $unidad->numero }}</h1>
    <p class="lead text-muted mb-5">Aquí puedes consultar tu estado de cuenta y realizar pagos.</p>

    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header text-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Saldo Total Pendiente
                </div>
                <div class="card-body text-center">
                    <h2 class="card-title display-4">
                        ${{ number_format($saldo_total_pendiente, 0, ',', '.') }}
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i>
                    Historial de Cobros
                </div>
                <div class="card-body">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th class="text-end">Monto Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cobros as $cobro)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($cobro->periodo)->translatedFormat('F Y') }}</td>
                                    <td class="text-end">${{ number_format($cobro->monto_total, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        @if ($cobro->estado === 'pagado')
                                            <span class="badge bg-success">Pagado</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="#" class="btn btn-primary btn-sm">
                                            Ver Detalle / Pagar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No tienes cobros registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection