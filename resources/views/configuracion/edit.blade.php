@extends('layout')

@section('title', 'Configuración del Sistema')

@section('content')
<div class="container">
    <h1 class="mb-4">Configuración del Sistema</h1>
    <p class="text-muted">Estos valores afectan a todos los condominios y se usan en los cálculos de cobros y multas.</p>

    <div class="card">
        <div class="card-header">
            Parámetros Generales
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('configuracion.update') }}">
                @csrf

                <div class="row mb-3">
                    <label for="fondo_reserva_porcentaje" class="col-sm-4 col-form-label">Porcentaje Fondo de Reserva (%)</label>
                    <div class="col-sm-8">
                        <input type="number" step="0.01" class="form-control" id="fondo_reserva_porcentaje" name="fondo_reserva_porcentaje" value="{{ old('fondo_reserva_porcentaje', $configuraciones['fondo_reserva_porcentaje'] ?? '10.00') }}">
                        <small class="form-text text-muted">Valor usado para calcular el fondo de reserva sobre el gasto común.</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="interes_mora_mensual" class="col-sm-4 col-form-label">Interés por Mora (mensual, %)</label>
                    <div class="col-sm-8">
                        <input type="number" step="0.01" class="form-control" id="interes_mora_mensual" name="interes_mora_mensual" value="{{ old('interes_mora_mensual', $configuraciones['interes_mora_mensual'] ?? '1.50') }}">
                        <small class="form-text text-muted">Interés que se aplicará mensualmente sobre saldos pendientes.</small>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection