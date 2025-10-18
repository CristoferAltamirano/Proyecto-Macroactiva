@extends('layout')

@section('title', 'Reporte de Gastos')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h1 class="h3 mb-0"><i class="bi bi-cash-coin me-2"></i>Reporte de Gastos Mensuales</h1>
    </div>
    <div class="card-body">
        <form action="{{ route('reportes.gastos') }}" method="GET" class="mb-4">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="periodo" class="form-label">Seleccionar Periodo</label>
                    <input type="month" class="form-control" id="periodo" name="periodo" value="{{ $periodoSeleccionado->format('Y-m') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gastos as $gasto)
                        <tr>
                            <td>{{ $gasto->descripcion }}</td>
                            <td><span class="badge {{ $gasto->tipo == 'ordinario' ? 'bg-info' : 'bg-warning' }}">{{ ucfirst($gasto->tipo) }}</span></td>
                            <td>{{ $gasto->fecha_gasto->format('d/m/Y') }}</td>
                            <td class="text-end">${{ number_format($gasto->monto, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No hay gastos registrados para este periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">TOTAL GASTOS DEL PERIODO</td>
                        <td class="text-end fw-bold h5">${{ number_format($totalGastos, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection