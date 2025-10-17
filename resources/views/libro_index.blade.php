@extends('layout')
@section('title', 'Libro de Movimientos')
@section('content')
    @include('partials.flash')

    @php
        // Detección de rol super_admin sin depender de Spatie
        $user = auth()->user();
        $esSuper = method_exists($user, 'hasRole')
            ? $user->hasRole('super_admin')
            : (($user->role ?? null) === 'super_admin');

        $cantidadCondos = isset($condos) ? $condos->count() : 0;
        $tieneUnSoloCondo = $cantidadCondos === 1;
        $sinCondominiosAsignados = !$esSuper && $cantidadCondos === 0;
    @endphp

    <style>
        /* ===== Contenedor principal ===== */
        .filters {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
            justify-content: center; /* Centrar filtros */
        }
        .filters .control {
            padding: 10px;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            width: 200px;
        }
        .filters button,
        .filters a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: .5rem;
            background: var(--primary);
            color: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: var(--shadow-xs);
            transition: transform .15s, box-shadow .15s, background .15s;
        }
        .filters button:hover,
        .filters a:hover {
            background: var(--primary-hover);
            box-shadow: var(--shadow-sm);
        }
        .card {
            padding: 20px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            text-align: center;
        }
        .card-table {
            padding: 20px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        .card-table h3 {
            text-align: center;
            margin-bottom: 20px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table thead th, table tbody td {
            padding: 12px; text-align: center; border: 1px solid #E5E7EB;
        }
        table thead { background: var(--primary); color: #fff; }
        table tbody tr:hover { background: #F9FAFB; }
        .muted { color: var(--muted); }
        .alert {
            padding: 14px 16px;
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            color: #92400E;
            margin-bottom: 16px;
        }
    </style>

    <div class="card">
        <h3 class="text-center">Libro de Movimientos</h3>

        @if ($sinCondominiosAsignados)
            <div class="alert">
                Tu usuario no tiene condominios asignados. Solicita a un <strong>super_admin</strong> que te asigne uno para ver el libro.
            </div>
        @endif

        <!-- Filtros y Botones -->
        <form class="filters" method="GET" action="{{ route('admin.libro.panel') }}">
            <label>Condominio
                @if ($esSuper)
                    {{-- SUPER ADMIN: puede ver "Todos" y elegir cualquier condominio --}}
                    <select name="id_condominio" class="control">
                        <option value="">Todos</option>
                        @foreach ($condos as $c)
                            <option value="{{ $c->id_condominio }}" @selected((string)request('id_condominio') === (string)$c->id_condominio)>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                @else
                    {{-- ADMIN: solo los suyos; si hay uno, lo fijo y oculto input --}}
                    @if ($tieneUnSoloCondo)
                        @php $c = $condos->first(); @endphp
                        <input type="hidden" name="id_condominio" value="{{ $c->id_condominio }}">
                        <input class="control" value="{{ $c->nombre }}" disabled>
                    @else
                        <select name="id_condominio" class="control" @if(!$sinCondominiosAsignados) required @endif @if($sinCondominiosAsignados) disabled @endif>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}" @selected((string)request('id_condominio') === (string)$c->id_condominio)>
                                    {{ $c->nombre }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                @endif
            </label>

            <label>Desde
                <input type="date" name="desde" value="{{ request('desde') }}" class="control" @if($sinCondominiosAsignados) disabled @endif>
            </label>
            <label>Hasta
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="control" @if($sinCondominiosAsignados) disabled @endif>
            </label>

            <button class="btn" @if($sinCondominiosAsignados) disabled @endif>Filtrar</button>

            {{-- Export respeta los mismos filtros y será validado en el Controller --}}
            <a class="btn btn--ghost"
               href="{{ route('admin.libro.export.csv', request()->all()) }}"
               @if($sinCondominiosAsignados) aria-disabled="true" onclick="return false;" class="btn btn--ghost disabled" @endif>
               Exportar CSV
            </a>
        </form>
    </div>

    <div class="card-table">
        <h3>Lista de Movimientos</h3>
        <table>
            <thead>
                <tr>
                    @if ($idCol)
                        <th>ID</th>
                    @endif
                    <th>Fecha</th>
                    <th>Condominio</th>
                    <th>Cuenta</th>
                    <th>Debe</th>
                    <th>Haber</th>
                    <th>Glosa</th>
                    <th>Ref</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movs as $m)
                    <tr>
                        @if ($idCol)
                            <td>{{ $m->id ?? '-' }}</td>
                        @endif
                        <td>{{ \Illuminate\Support\Str::limit((string) $m->fecha, 10, '') }}</td>
                        <td>{{ $m->condominio ?? $m->id_condominio }}</td>
                        <td>{{ $m->cta_codigo }} — {{ $m->cta_nombre }}</td>
                        <td>${{ number_format($m->debe, 2, ',', '.') }}</td>
                        <td>${{ number_format($m->haber, 2, ',', '.') }}</td>
                        <td>{{ $m->glosa }}</td>
                        <td>{{ $m->ref_tabla }}#{{ $m->ref_id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $idCol ? 8 : 7 }}" class="muted">
                            @if ($sinCondominiosAsignados)
                                No puedes ver movimientos porque no tienes condominios asignados.
                            @else
                                Sin movimientos para los filtros seleccionados.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
