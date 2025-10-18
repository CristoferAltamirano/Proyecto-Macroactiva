@extends('layout')

@section('title', 'Plan de Cuentas Contables')

@section('content')
<div class="container">
    <h1 class="mb-4">Plan de Cuentas Contables</h1>

    <div class="row">
        {{-- Formulario para Crear/Editar --}}
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">
                    @if(isset($cuenta_a_editar))
                        Editar Cuenta
                    @else
                        Crear Nueva Cuenta
                    @endif
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ isset($cuenta_a_editar) ? route('cuentas.update', $cuenta_a_editar->id_cta_contable) : route('cuentas.store') }}">
                        @csrf
                        @if(isset($cuenta_a_editar))
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="codigo" class="form-label">Código</label>
                            <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $cuenta_a_editar->codigo ?? '') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Cuenta</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $cuenta_a_editar->nombre ?? '') }}" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">{{ isset($cuenta_a_editar) ? 'Actualizar' : 'Crear' }}</button>
                            @if(isset($cuenta_a_editar))
                                <a href="{{ route('cuentas.index') }}" class="btn btn-secondary">Cancelar Edición</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Importar/Exportar --}}
            <div class="card">
                <div class="card-header">
                    Importar / Exportar
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('cuentas.import') }}" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="file" name="archivo" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="submit">Importar</button>
                        </div>
                        <select name="modo" class="form-select mt-2">
                            <option value="insert">Insertar solo nuevas</option>
                            <option value="upsert">Insertar y Actualizar</option>
                            <option value="replace">Reemplazar Todo</option>
                        </select>
                    </form>
                    <hr>
                    <div class="d-grid">
                        <a href="{{ route('cuentas.export') }}" class="btn btn-outline-success">Exportar a CSV</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Listado de Cuentas --}}
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    Listado de Cuentas
                </div>
                <div class="card-body">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cuentas as $c)
                                <tr>
                                    <td>{{ $c->codigo }}</td>
                                    <td>{{ $c->nombre }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('cuentas.edit', $c->id_cta_contable) }}" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('cuentas.destroy', $c->id_cta_contable) }}" class="d-inline" onsubmit="return confirm('¿Estás seguro?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center">No hay cuentas contables.</td>
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