@extends('layout')
@section('title', 'Copropietarios')

@section('content')
    @include('partials.flash')

    {{-- Errores de validación (opcional) --}}
    @if ($errors->any())
        <div class="card" style="background:#fff3f3;border:1px solid #fecaca;color:#991b1b">
            <ul style="margin:0 0 0 18px;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
      $eligibles = ($usuariosElegibles ?? $usuarios ?? collect());
      $sinElegibles = $eligibles->isEmpty();
    @endphp

    <style>
        /* ===== Formulario 1 → 2 columnas ===== */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px 22px;
            margin-top: 18px;
            max-width: 980px;
            margin-left: auto;
            margin-right: auto;
        }
        @media (min-width:720px) { .form-grid-2 { grid-template-columns: 1fr 1fr; } }
        .col-span-2 { grid-column: 1 / -1; }
        .form-field { display:flex; flex-direction:column; align-items:center; gap:6px; }
        .form-field label { font-weight:600; text-align:center; }

        /* Controles compactos y centrados */
        .control {
            width:100%; max-width:340px; padding:6px 8px; font-size:.92rem;
            border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-xs);
            outline:none; text-align:center; margin:0 auto; display:block;
        }
        select.control { text-align-last:center; }
        .control:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(6,182,212,.15); }
        .center { display:flex; justify-content:center; align-items:center; }

        h3, label, tr, tr th { text-align:center; }

        /* Tabla con contenedor y encabezado fijo */
        .table-wrap { width:100%; overflow:auto; border:1px solid var(--border); border-radius:var(--radius); background:var(--card); box-shadow:var(--shadow-xs); }
        .table-flat { width:100%; border-collapse:collapse; }
        .table-flat thead th, .table-flat tbody td { padding:10px 12px; border-bottom:1px solid var(--border); white-space:nowrap; text-align:center; }
        .table-flat thead th { position:sticky; top:0; background:#f8fafc; z-index:1; }
        .table-flat tbody tr:hover { background:#f9fafb; }

        /* Form inline (terminar) */
        .inline-form { display:flex; gap:8px; align-items:center; justify-content:center; flex-wrap:wrap; }
        .inline-form input { padding:6px 8px; border:1px solid var(--border); border-radius:10px; text-align:center; }

        /* Aviso */
        .note {
            background:#fff8e1; border:1px solid #fde68a; color:#7c5800;
            padding:10px 12px; border-radius:10px; margin:8px 0; text-align:center;
        }
    </style>

    <div class="card">
        <h3>Nueva relación copropietaria</h3>

        @if($sinElegibles)
          <div class="note">
            No hay <strong>usuarios copropietarios elegibles</strong> sin vigencia activa en tus condominios.
            Crea el usuario en <strong>Usuarios</strong> (tipo <em>copropietario</em>) o termina alguna vigencia vigente.
          </div>
        @endif

        <form method="POST" action="{{ route('admin.coprop.store') }}" class="form-grid-2">
            @csrf

            <div class="form-field">
                <label for="id_unidad">Número unidad:</label>
                <select id="id_unidad" name="id_unidad" required class="control">
                    @foreach ($unidades as $u)
                        <option value="{{ $u->id_unidad }}" @selected(old('id_unidad')==$u->id_unidad)>
                            #{{ $u->id_unidad }} {{ $u->codigo ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="id_usuario">Usuario:</label>
                <select id="id_usuario" name="id_usuario" required class="control" {{ $sinElegibles ? 'disabled' : '' }}>
                    @foreach ($eligibles as $u)
                        <option value="{{ $u->id_usuario }}" @selected(old('id_usuario')==$u->id_usuario)>
                            {{ $u->nombres }} {{ $u->apellidos }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="porcentaje">Participación (%):</label>
                <input id="porcentaje" name="porcentaje" type="number" min="0" max="100" step="0.001"
                       placeholder="% participación" required class="control" inputmode="decimal"
                       value="{{ old('porcentaje') }}">
            </div>

            <div class="form-field">
                <label for="desde">Fecha:</label>
                <input id="desde" type="date" name="desde" required class="control" value="{{ old('desde') }}">
            </div>

            <div class="col-span-2 center" style="margin-top:6px;">
                <button class="btn" {{ $sinElegibles ? 'disabled' : '' }}>Guardar</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Vigencias recientes</h3>
        <div class="table-wrap" style="margin-top:10px">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Unidad</th>
                        <th>Copropietario</th>
                        <th>%</th>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th>Terminar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ult as $c)
                        <tr>
                            <td>{{ $c->id_coprop }}</td>
                            <td>{{ $c->unidad ?? $c->id_unidad }}</td>
                            <td>{{ $c->nombres }} {{ $c->apellidos }}</td>
                            <td>{{ rtrim(rtrim(number_format((float)$c->porcentaje, 3, '.', ''), '0'), '.') }}</td>
                            <td>{{ $c->desde }}</td>
                            <td>{{ $c->hasta ?? 'Vigente' }}</td>
                            <td>
                                @if (!$c->hasta)
                                    <form method="POST" action="{{ route('admin.coprop.terminar', $c->id_coprop) }}" class="inline-form">
                                        @csrf
                                        <input type="date" name="hasta" required>
                                        <button class="btn">Terminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
