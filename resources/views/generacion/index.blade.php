@extends('layout')

@section('title', 'Generar Cobros')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form action="{{ route('generacion.generar') }}" method="POST">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h1 class="h4 mb-0"><i class="bi bi-calculator-fill me-2"></i>Generador de Cobros Mensuales</h1>
                </div>
                <div class="card-body">
                    <p class="card-text">Selecciona el mes y año para el cual deseas calcular y generar los cobros de gastos comunes para todas las unidades activas.</p>

                    <div class="mb-3">
                        <label for="periodo" class="form-label">Periodo a Generar</label>
                        <input type="month" class="form-control @error('periodo') is-invalid @enderror" id="periodo" name="periodo" value="{{ date('Y-m') }}" required>
                        @error('periodo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-warning">
                        <strong>¡Atención!</strong> Este proceso es irreversible. Se calculará el total de gastos del periodo seleccionado y se aplicará el prorrateo a cada unidad.
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-gear-wide-connected me-1"></i>
                        Generar Cobros
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection