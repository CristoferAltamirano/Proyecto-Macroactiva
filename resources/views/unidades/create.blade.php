@extends('layout')

@section('title', 'Nueva Unidad')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <form action="{{ route('unidades.store') }}" method="POST">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0"><i class="bi bi-building-add me-2"></i>Crear Nueva Unidad</h1>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="numero" class="form-label">Número de Unidad/Depto</label>
                            <input type="text" class="form-control @error('numero') is-invalid @enderror" id="numero" name="numero" value="{{ old('numero') }}" required>
                            @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="propietario" class="form-label">Nombre del Propietario</label>
                            <input type="text" class="form-control @error('propietario') is-invalid @enderror" id="propietario" name="propietario" value="{{ old('propietario') }}" required>
                            @error('propietario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="residente" class="form-label">Nombre del Residente (si es diferente)</label>
                        <input type="text" class="form-control @error('residente') is-invalid @enderror" id="residente" name="residente" value="{{ old('residente') }}" required>
                        @error('residente') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                            <input type="text" class="form-control @error('telefono') is-invalid @enderror" id="telefono" name="telefono" value="{{ old('telefono') }}">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- ✨ ¡AQUÍ ESTÁ LA MAGIA DE ROLES! ✨ --}}
                    @if (Auth::user()->role === 'super-admin')
                        <div class="mb-3">
                            <label for="prorrateo" class="form-label fw-bold text-danger">Prorrateo (Alicuota)</label>
                            <input type="text" class="form-control @error('prorrateo') is-invalid @enderror" id="prorrateo" name="prorrateo" value="{{ old('prorrateo', '0.00000') }}" required>
                            <div class="form-text">Ejemplo: para 12,5%, ingresar 0.12500</div>
                            @error('prorrateo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select @error('estado') is-invalid @enderror" id="estado" name="estado" required>
                            <option value="Activo" selected>Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                        @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('unidades.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Unidad</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection