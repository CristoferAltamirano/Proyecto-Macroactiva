@extends('layout')

@section('title', 'Reporte de Morosidad')

@section('content')
<div class="container">
    <h1 class="mb-4">Reporte de Morosidad</h1>
    <div class="card">
        <div class="card-header">
            Unidades con Deudas Pendientes
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Unidad</th>
                        <th>Propietario</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th class="text-end">Deuda Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reporte as $item)
                        <tr>
                            <td>{{ $item['unidad']->numero }}</td>
                            <td>{{ $item['unidad']->propietario }}</td>
                            <td>{{ $item['unidad']->email }}</td>
                            <td>{{ $item['unidad']->telefono }}</td>
                            <td class="text-end">${{ number_format($item['total_deuda'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No hay unidades con deudas pendientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection