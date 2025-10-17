@extends('layout')
@section('title', 'Remuneraciones')

@section('content')
    <style>
        /* ===== Maquetación local (solo esta vista) ===== */
        .card-title,
        .section-title {
            text-align: center;
            margin: 0 0 10px 0;
        }

        /* Grid del formulario: 1 → 2 → 3 columnas */
        .form-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: 1fr;
            /* mobile */
        }

        @media (min-width: 720px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

            /* tablet */
        }

        @media (min-width: 1100px) {
            .form-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }

            /* desktop (3 cols) */
        }

        /* Labels apilados y centrados */
        .form-grid>label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-weight: 500;
            color: var(--text);
            text-align: center;
        }

        /* ===== Controles compactos y centrados ===== */
        .form-grid input,
        .form-grid select {
            width: 100%;
            padding: 6px 8px;
            /* MÁS PEQUEÑOS */
            font-size: .92rem;
            /* Tipografía levemente menor */
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-xs);
            outline: none;
            text-align: center;
        }

        .form-grid select {
            text-align-last: center;
        }

        .form-grid input:focus,
        .form-grid select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        /* Campos que deben ocupar el ancho completo del grid */
        .full {
            grid-column: 1 / -1;
        }

        /* Botón centrado al final del form */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin-top: 4px;
        }

        /* ===== Tabla inferior sin card ===== */
        .table-section {
            margin-top: 20px;
        }

        .table-wrap {
            width: 100%;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--card);
            box-shadow: var(--shadow-xs);
        }

        .table-flat {
            width: 100%;
            border-collapse: collapse;
        }

        .table-flat th,
        .table-flat td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: center;
        }

        .table-flat thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc;
        }

        .table-flat tbody tr:hover {
            background: #f9fafb;
        }

        /* Formulario inline en la tabla (acción Pagar) también compacto */
        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .inline-form input,
        .inline-form select {
            padding: 6px 8px;
            font-size: .92rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            text-align: center;
        }

        .inline-form select {
            text-align-last: center;
        }
    </style>

    @if (session('ok'))
        <div class="card" style="text-align:center"><strong>{{ session('ok') }}</strong></div>
    @endif

    <div class="card">
        <h3 class="card-title">Registrar remuneración</h3><br>
        <form method="POST" action="{{ route('admin.remu.store') }}" class="form-grid">
            @csrf

            <label>Trabajador
                <select name="id_trabajador" required>
                    @foreach ($trab as $t)
                        <option value="{{ $t->id_trabajador }}">{{ $t->condominio }} — {{ $t->apellidos }},
                            {{ $t->nombres }}</option>
                    @endforeach
                </select>
            </label>

            <label>Tipo
                <select name="tipo">
                    <option value="mensual">Mensual</option>
                    <option value="bono">Bono</option>
                    <option value="retroactivo">Retroactivo</option>
                    <option value="finiquito">Finiquito</option>
                    <option value="otro">Otro</option>
                </select>
            </label>

            <label>Periodo (AAAAMM)
                <input name="periodo" pattern="[0-9]{6}" required>
            </label>

            <label>Bruto
                <input type="number" step="0.01" name="bruto" required>
            </label>

            <label>Imposiciones
                <input type="number" step="0.01" name="imposiciones" value="0" required>
            </label>

            <label>Descuentos
                <input type="number" step="0.01" name="descuentos" value="0" required>
            </label>

            <label>Líquido
                <input type="number" step="0.01" name="liquido" required>
            </label>

            <label>Fecha pago (opcional)
                <input type="date" name="fecha_pago">
            </label>

            <label>Método pago
                <select name="id_metodo_pago">
                    <option value="">(sin pago)</option>
                    @foreach ($metodos as $m)
                        <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="full">Comprobante URL
                <input type="url" name="comprobante_url">
            </label>

            <label class="full">Observación
                <input name="observacion">
            </label>

            <div class="form-actions">
                <button class="btn">Guardar</button>
            </div>
        </form><br>
    </div>

    <div class="table-section">
        <h3 class="section-title">Últimas remuneraciones</h3><br>
        <div class="table-wrap">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Condominio</th>
                        <th>Trabajador</th>
                        <th>Periodo</th>
                        <th>Bruto</th>
                        <th>Líquido</th>
                        <th>Pago</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remu as $r)
                        <tr>
                            <td>{{ $r->id_remuneracion }}</td>
                            <td>{{ $r->condominio }}</td>
                            <td>{{ $r->apellidos }}, {{ $r->nombres }}</td>
                            <td>{{ $r->periodo }}</td>
                            <td>${{ number_format($r->bruto, 0, ',', '.') }}</td>
                            <td>${{ number_format($r->liquido, 0, ',', '.') }}</td>
                            <td>{{ $r->fecha_pago ?: '—' }}</td>
                            <td>
                                @if (!$r->fecha_pago)
                                    <form method="POST" action="{{ route('admin.remu.pagar', $r->id_remuneracion) }}"
                                        class="inline-form">
                                        @csrf
                                        <input type="date" name="fecha_pago" required>
                                        <select name="id_metodo_pago" required>
                                            @foreach ($metodos as $m)
                                                <option value="{{ $m->id_metodo_pago }}">{{ $m->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn">Pagar</button>
                                    </form>
                                @else
                                    <span class="muted">Pagada</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table><br>
        </div>
    </div>
@endsection
