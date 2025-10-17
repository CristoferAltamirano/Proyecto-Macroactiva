@extends('layout')
@section('title', 'Panel')
@section('content')
    @include('partials.flash')

    <div class="grid">
        <div class="card">
            <h3>Atajos</h3>
            @if (in_array(auth()->user()->tipo_usuario, ['super_admin', 'admin']))
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="{{ route('admin.cobros.panel') }}">Cobros</a>
                    <a class="btn" href="{{ route('pagos.panel') }}">Pagos</a>
                    <a class="btn" href="{{ route('admin.gastos.panel') }}">Gastos</a>
                    <a class="btn" href="{{ route('admin.proveedores.panel') }}">Proveedores</a>
                    <a class="btn" href="{{ route('admin.prorrateo.panel') }}">Prorrateo</a>
                    <a class="btn" href="{{ route('admin.fr.panel') }}">Fondo Reserva</a>
                    <a class="btn" href="{{ route('admin.audit.panel') }}">Auditoría</a>
                    <a class="btn" href="{{ route('admin.export.panel') }}">Exportar</a>
                </div>
            @else
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="{{ route('mi.cuenta') }}">Mi cuenta</a>
                    <a class="btn" href="{{ route('estado.cuenta') }}">Estado de cuenta</a>
                </div>
            @endif
        </div>
    </div>
@endsection
