@extends('layout')

@section('title', 'Gestión de Gastos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-receipt me-2"></i>Gestión de Gastos Comunes</h1>
    <a href="{{ route('gastos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Nuevo Gasto
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Descripción</th>
                        <th class="text-end">Monto</th>
                        <th>Tipo</th>
                        <th>Fecha del Gasto</th>
                        <th>Periodo</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gastos as $gasto)
                        <tr>
                            <td>{{ Str::limit($gasto->descripcion, 40) }}</td>
                            <td class="text-end">${{ number_format($gasto->monto, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $gasto->tipo == 'ordinario' ? 'bg-info' : 'bg-warning text-dark' }}">
                                    {{ ucfirst($gasto->tipo) }}
                                </span>
                            </td>
                            <td>{{ $gasto->fecha_gasto->format('d/m/Y') }}</td>
                            <td>{{ Str::ucfirst($gasto->periodo_gasto->translatedFormat('F Y')) }}</td>
                            <td class="text-center">
                                <div class="btn-group">
                                    {{-- BOTÓN DE EDITAR --}}
                                    <a href="{{ route('gastos.edit', $gasto->id) }}" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    
                                    {{-- BOTÓN DE ELIMINAR --}}
                                    <form action="{{ route('gastos.destroy', $gasto->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este gasto?');">
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
                            <td colspan="6" class="text-center">No hay gastos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
         <div class="d-flex justify-content-center">
            {{ $gastos->links() }}
        </div>
    </div>
</div>
@endsection