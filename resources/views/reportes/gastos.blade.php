@extends('layout')

@section('title', 'Reporte de Gastos Mensuales')

@section('content')
<div class="container">
    <h1 class="mb-4">Reporte de Gastos Mensuales</h1>

    <div class="card mb-4">
        <div class="card-header">
            Filtrar por Mes y Año
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reportes.gastos') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mes</label>
                    <select name="mes" id="mes" class="form-select">
                        @for ($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ $mes == $i ? 'selected' : '' }}>
                                {{ DateTime::createFromFormat('!m', $i)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="ano" class="form-label">Año</label>
                    <input type="number" name="ano" id="ano" class="form-control" value="{{ $ano }}" min="2000">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Desglose de Gastos para {{ DateTime::createFromFormat('!m', $mes)->format('F') }} de {{ $ano }}
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
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
                            <td>{{ $gasto->tipo }}</td>
                            <td>{{ $gasto->fecha_gasto->format('d/m/Y') }}</td>
                            <td class="text-end">${{ number_format($gasto->monto, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No hay gastos para el período seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total:</th>
                        <th class="text-end">${{ number_format($totalGastos, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection