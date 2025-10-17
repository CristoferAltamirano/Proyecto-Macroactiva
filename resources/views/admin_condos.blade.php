@extends('layout')
@section('title', 'Condominios')

@section('content')
    @include('partials.flash')

    {{-- ✨ Errores de validación --}}
    @if ($errors->any())
        <div class="card" style="background:#fff3f3;border:1px solid #fecaca;color:#991b1b">
            <ul style="margin:0 0 0 18px;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <style>
        /* ===== Formulario: 1 → 2 → 3 columnas ===== */
        .form-grid {
            display: grid;
            gap: 14px 24px;
            grid-template-columns: 1fr;
            max-width: 980px;
            margin: 0 auto;
        }

        @media (min-width: 720px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (min-width: 1100px) {
            .form-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-weight: 600;
            text-align: center;
            font-size: 1rem;
        }

        .field label::after {
            content: ':';
        }

        /* Inputs compactos y centrados */
        .control {
            padding: 8px 10px;
            font-size: var(--font-size);
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-xs);
            outline: none;
        }

        select.control {
            text-align-last: center;
        }

        .control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .center {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .full {
            grid-column: 1 / -1;
        }

        /* Fila centrada para Tipo de cuenta + N° cuenta */
        .row-center {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .row-center .field {
            min-width: 240px;
            max-width: 360px;
            width: 100%;
        }

        /* ===== Tabla con scroll y encabezado fijo ===== */
        .table-card .section-title {
            text-align: center;
            margin: 6px 0 10px;
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
            text-align: center;
            white-space: nowrap;
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
    </style>

    <div class="card">
        <h3 class="center" style="margin-top:4px">Nuevo/Editar condominio</h3><br>

        <form method="POST" action="{{ route('admin.condos.store') }}" class="form-grid" id="frmCondo">
            @csrf
            <input type="hidden" name="id_condominio" id="id_condominio" value="{{ old('id_condominio', '') }}">

            <div class="field">
                <label>Nombre</label>
                <input name="nombre" class="control" value="{{ old('nombre', '') }}" required
                    placeholder="Ej: Las Araucarias">
            </div>

            <div class="field">
                <label>RUT base</label>
                <input name="rut_base" class="control" value="{{ old('rut_base', '') }}" placeholder="Ej: 10828666">
            </div>

            <div class="field">
                <label>Dígito verificador</label>
                <input name="dv" class="control" value="{{ old('dv', '') }}" placeholder="K / 0-9">
            </div>

            <div class="field">
                <label>Dirección</label>
                <input name="direccion" class="control" value="{{ old('direccion', '') }}"
                    placeholder="Ej: Los Copihues 123">
            </div>

            <div class="field">
                <label>Comuna</label>
                <input name="comuna" class="control" value="{{ old('comuna', '') }}" placeholder="Ej: Padre Las Casas">
            </div>

            <div class="field">
                <label>Región</label>
                <input name="region" class="control" value="{{ old('region', '') }}" placeholder="Ej: La Araucanía">
            </div>

            <div class="field">
                <label>Correo</label>
                <input name="email" type="email" class="control" value="{{ old('email', '') }}"
                    placeholder="correo@dominio.cl">
            </div>

            <div class="field">
                <label>Nro teléfono</label>
                <input name="telefono" class="control" value="{{ old('telefono', '') }}" placeholder="569xxxxxxxx">
            </div>

            <div class="field">
                <label>Banco</label>
                <input name="banco" class="control" value="{{ old('banco', '') }}" placeholder="Banco Estado">
            </div>

            {{-- 🧩 Fila centrada con los dos campos solicitados --}}
            <div class="row-center">
                <div class="field">
                    <label>Tipo de cuenta</label>
                    @php $tc = old('tipo_cuenta',''); @endphp
                    <select name="tipo_cuenta" class="control">
                        <option value="" {{ $tc === '' ? 'selected' : '' }}>—</option>
                        <option value="vista" {{ $tc === 'vista' ? 'selected' : '' }}>vista</option>
                        <option value="corriente" {{ $tc === 'corriente' ? 'selected' : '' }}>corriente</option>
                        <option value="ahorro" {{ $tc === 'ahorro' ? 'selected' : '' }}>ahorro</option>
                    </select>
                </div>

                <div class="field">
                    <label>N° cuenta</label>
                    <input name="num_cuenta" class="control" value="{{ old('num_cuenta', '') }}"
                        placeholder="134528576938">
                </div>
            </div>

            <div class="center full" style="gap:10px;">
                <button type="button" class="btn" onclick="resetForm()">Nuevo</button>
                <button class="btn">Guardar</button>
            </div>
        </form><br>
    </div>

    <div class="card table-card">
        <h3 class="section-title">Listado condominios</h3><br>
        <div class="table-wrap">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Comuna</th>
                        <th>Región</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Banco</th>
                        <th>Cuenta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($condos as $c)
                        @php
                            $dv = $c->dv ?? ($c->rut_dv ?? ($c->digito_verificador ?? null));
                        @endphp
                        <tr>
                            <td>{{ $c->id_condominio ?? '' }}</td>
                            <td>{{ $c->nombre ?? '' }}</td>
                            <td>{{ $c->rut_base ?? '' }}@if (!empty($dv))
                                    -{{ $dv }}
                                @endif
                            </td>
                            <td>{{ $c->comuna ?? '' }}</td>
                            <td>{{ $c->region ?? '' }}</td>
                            <td>{{ $c->telefono ?? '' }}</td>
                            <td class="muted">{{ $c->email ?? '' }}</td>
                            <td>{{ $c->banco ?? '' }}</td>
                            <td>{{ $c->tipo_cuenta ?? '' }} {{ $c->num_cuenta ?? '' }}</td>
                            <td>
                                <button class="btn" type="button"
                                    onclick='fillForm(@json($c))'>Editar</button>
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="muted">Sin condominios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div><br>
        </div>

        <script>
            function resetForm() {
                const f = document.getElementById('frmCondo');
                f.reset();
                document.getElementById('id_condominio').value = '';
            }

            function fillForm(c) {
                document.getElementById('id_condominio').value = c.id_condominio ?? '';
                const map = {
                    nombre: c.nombre,
                    rut_base: c.rut_base,
                    dv: (c.dv ?? c.rut_dv ?? c.digito_verificador ?? ''),
                    direccion: c.direccion,
                    comuna: c.comuna,
                    region: c.region,
                    telefono: c.telefono,
                    email: c.email,
                    banco: c.banco,
                    tipo_cuenta: c.tipo_cuenta,
                    num_cuenta: c.num_cuenta
                };
                Object.entries(map).forEach(([k, v]) => {
                    const el = document.querySelector('[name="' + k + '"]');
                    if (!el) return;
                    el.value = v ?? '';
                });
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        </script>
    @endsection
