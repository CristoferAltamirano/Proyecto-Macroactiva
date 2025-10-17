@extends('layout')

@section('title', 'Nuevo Gasto Común')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <form action="{{ route('gastos.store') }}" method="POST">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Gasto</h1>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción del Gasto</label>
                        <input type="text" class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" value="{{ old('descripcion') }}" required placeholder="Ej: Sueldo conserje, Reparación bomba de agua, etc.">
                        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="monto" class="form-label">Monto (CLP)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control @error('monto') is-invalid @enderror" id="monto" name="monto" value="{{ old('monto') }}" required min="0">
                            </div>
                            @error('monto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo de Gasto</label>
                            <select class="form-select @error('tipo') is-invalid @enderror" id="tipo" name="tipo" required>
                                <option value="ordinario" selected>Ordinario</option>
                                <option value="extraordinario">Extraordinario</option>
                            </select>
                            @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_gasto" class="form-label">Fecha del Gasto</label>
                            <input type="date" class="form-control @error('fecha_gasto') is-invalid @enderror" id="fecha_gasto" name="fecha_gasto" value="{{ old('fecha_gasto', date('Y-m-d')) }}" required>
                            @error('fecha_gasto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="periodo_gasto" class="form-label">Periodo al que Imputar</label>
                            <input type="month" class="form-control @error('periodo_gasto') is-invalid @enderror" id="periodo_gasto_display" value="{{ old('periodo_gasto', date('Y-m')) }}" onchange="document.getElementById('periodo_gasto').value = this.value + '-01'">
                            <input type="hidden" name="periodo_gasto" id="periodo_gasto" value="{{ old('periodo_gasto', date('Y-m-01')) }}">
                            @error('periodo_gasto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('gastos.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Gasto</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection