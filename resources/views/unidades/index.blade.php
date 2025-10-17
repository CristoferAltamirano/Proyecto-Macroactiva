@extends('layout')

@section('title', 'Gestión de Unidades')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-building me-2"></i>Gestión de Unidades</h1>
    <a href="{{ route('unidades.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Nueva Unidad
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Propietario</th>
                        <th scope="col">Residente</th>
                        <th scope="col">Prorrateo (%)</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unidades as $unidad)
                        <tr>
                            <th scope="row">{{ $unidad->numero }}</th>
                            <td>{{ $unidad->propietario }}</td>
                            <td>{{ $unidad->residente }}</td>
                            <td>{{ number_format($unidad->prorrateo * 100, 3, ',', '.') }}%</td>
                            <td>
                                @if ($unidad->estado == 'Activo')
                                    <span class="badge bg-success">{{ $unidad->estado }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $unidad->estado }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="{{ route('unidades.edit', $unidad->id) }}" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form action="{{ route('unidades.destroy', $unidad->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta unidad?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No hay unidades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-center">
            {{ $unidades->links() }}
        </div>
    </div>
</div>
@endsection