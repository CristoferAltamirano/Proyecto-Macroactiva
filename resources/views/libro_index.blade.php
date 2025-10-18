@extends('layout')

@section('title', 'Libro de Movimientos Contables')

@section('content')
<div class="container">
    <h1 class="mb-4">Libro de Movimientos Contables</h1>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filtros --}}
    <div class="card mb-4">
        <div class="card-header">
            Filtros
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('libro.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="id_condominio" class="form-label">Condominio</label>
                    <select name="id_condominio" id="id_condominio" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($condos as $c)
                            <option value="{{ $c->id_condominio }}" @selected(request('id_condominio') == $c->id_condominio)>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="desde" class="form-label">Desde</label>
                    <input type="date" name="desde" id="desde" value="{{ request('desde') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="hasta" class="form-label">Hasta</label>
                    <input type="date" name="hasta" id="hasta" value="{{ request('hasta') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Movimientos --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            Lista de Movimientos
            <a href="{{ route('libro.export.csv', request()->all()) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar CSV
            </a>
        </div>
        <div class="card-body">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        @if ($idCol) <th>ID</th> @endif
                        <th>Fecha</th>
                        <th>Condominio</th>
                        <th>Cuenta Contable</th>
                        <th class="text-end">Debe</th>
                        <th class="text-end">Haber</th>
                        <th>Glosa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movs as $m)
                        <tr>
                            @if ($idCol) <td>{{ $m->id ?? '-' }}</td> @endif
                            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                            <td>{{ $m->condominio ?? 'N/A' }}</td>
                            <td>{{ $m->cta_codigo }} - {{ $m->cta_nombre }}</td>
                            <td class="text-end text-success fw-bold">
                                @if($m->debe > 0)
                                    ${{ number_format($m->debe, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="text-end text-danger fw-bold">
                                @if($m->haber > 0)
                                    ${{ number_format($m->haber, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="text-muted">{{ $m->glosa }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $idCol ? 7 : 6 }}" class="text-center p-4">
                                No se encontraron movimientos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection