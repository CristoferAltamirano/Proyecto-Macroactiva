@extends('layout')

@section('title', 'Revisión de Cobros')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-journal-check me-2"></i>Revisión de Cobros</h1>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <form action="{{ route('cobros.index') }}" method="GET">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="periodo" class="form-label">Seleccionar Periodo</label>
                    <input type="month" class="form-control" id="periodo" name="periodo" value="{{ $periodoSeleccionado->format('Y-m') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body">
        <h4 class="card-title">Cobros generados para: <span class="text-success">{{ Str::ucfirst($periodoSeleccionado->translatedFormat('F Y')) }}</span></h4>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Unidad</th>
                        <th>Propietario</th>
                        <th class="text-end">Gasto Común</th>
                        <th class="text-end">Fondo Reserva</th>
                        <th class="text-end">Total a Pagar</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cobros as $cobro)
                        <tr>
                            <td><strong>{{ $cobro->unidad->numero }}</strong></td>
                            <td>{{ $cobro->unidad->propietario }}</td>
                            <td class="text-end">${{ number_format($cobro->monto_gasto_comun, 0, ',', '.') }}</td>
                            <td class="text-end">${{ number_format($cobro->monto_fondo_reserva, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">${{ number_format($cobro->monto_total, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $cobro->estado == 'pendiente' ? 'bg-danger' : 'bg-success' }}">
                                    {{ ucfirst($cobro->estado) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-info" title="Ver Detalle">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>

                                    {{-- 👇 FORMULARIO PARA REGISTRAR PAGO 👇 --}}
                                    @if ($cobro->estado == 'pendiente')
                                        <form action="{{ route('cobros.pagar', $cobro->id) }}" method="POST" onsubmit="return confirm('¿Confirmas que has recibido el pago para esta unidad?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-success" title="Registrar Pago">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No se han generado cobros para el periodo seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection