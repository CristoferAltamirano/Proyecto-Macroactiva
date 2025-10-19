@extends('layout')

@section('title', 'Reporte de Morosidad')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h1 class="h3 mb-0"><i class="bi bi-person-exclamation me-2"></i>Reporte de Morosidad</h1>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Unidad</th>
                        <th>Propietario</th>
                        <th class="text-center">Meses Adeudados</th>
                        <th class="text-end">Deuda Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deudas as $deuda)
                        <tr>
                            <td><strong>{{ $deuda['unidad']->numero }}</strong></td>
                            <td>{{ $deuda['unidad']->propietario }}</td>
                            <td class="text-center">{{ $deuda['meses_adeudados'] }}</td>
                            <td class="text-end fw-bold text-danger">${{ number_format($deuda['total_deuda'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-success">¡Felicidades! No hay unidades con deudas pendientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection