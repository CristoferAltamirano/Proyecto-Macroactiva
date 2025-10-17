@extends('layout')

@section('title', 'Editar Unidad')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <form action="{{ route('unidades.update', $unidad->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h1 class="h4 mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Unidad: {{ $unidad->numero }}</h1>
                </div>
                <div class="card-body">
                    {{-- ... (campos numero, propietario, residente, email, telefono igual que en create) ... --}}
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="numero" class="form-label">Número de Unidad/Depto</label>
                            <input type="text" class="form-control" id="numero" name="numero" value="{{ old('numero', $unidad->numero) }}" required>
                        </div>
                         <div class="col-md-8 mb-3">
                            <label for="propietario" class="form-label">Nombre del Propietario</label>
                            <input type="text" class="form-control" id="propietario" name="propietario" value="{{ old('propietario', $unidad->propietario) }}" required>
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="residente" class="form-label">Nombre del Residente</label>
                        <input type="text" class="form-control" id="residente" name="residente" value="{{ old('residente', $unidad->residente) }}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $unidad->email) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" value="{{ old('telefono', $unidad->telefono) }}">
                        </div>
                    </div>

                    {{-- ✨ ¡AQUÍ ESTÁ LA MAGIA DE ROLES! ✨ --}}
                    <div class="mb-3">
                        <label for="prorrateo" class="form-label @if(Auth::user()->role === 'super-admin') fw-bold text-danger @endif">Prorrateo (Alicuota)</label>
                        <input type="text" class="form-control" id="prorrateo" name="prorrateo" value="{{ old('prorrateo', $unidad->prorrateo) }}" 
                               @if(Auth::user()->role !== 'super-admin') readonly @endif>
                        <div class="form-text">Ejemplo: para 12,5%, ingresar 0.12500</div>
                    </div>

                     <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="Activo" {{ old('estado', $unidad->estado) == 'Activo' ? 'selected' : '' }}>Activo</option>
                            <option value="Inactivo" {{ old('estado', $unidad->estado) == 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('unidades.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-warning">Actualizar Unidad</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection