@extends('layout')
@section('title', 'Proveedores')

@section('content')
    @if (session('ok'))
        <div class="card"><strong>{{ session('ok') }}</strong></div>
    @endif

    @php
        // ===== Contexto / rol
        $yo    = auth()->user();
        $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $isSA  = $rol === 'super_admin';
        $ctxId = (int) (session('ctx_condo_id') ?? 0);

        // Normalizamos la colección que viene del controller
        $proveedoresCol = collect($proveedores ?? []);

        /* Anti info cruzada: si es admin y tenemos ctx,
           dejamos SOLO proveedores cuyo registro indique ese condominio.
           Buscamos las columnas habituales: id_condominio / condominio_id.
           Si no existen, no filtramos (no rompemos). */
        if (!$isSA && $ctxId > 0 && $proveedoresCol->isNotEmpty()) {
            $proveedoresCol = $proveedoresCol->filter(function ($p) use ($ctxId) {
                // soporta objeto stdClass o array
                $cid = is_array($p)
                    ? ($p['id_condominio'] ?? ($p['condominio_id'] ?? null))
                    : ($p->id_condominio ?? ($p->condominio_id ?? null));
                // si no existe columna, no forzamos exclusión
                return is_null($cid) ? true : ((int) $cid === $ctxId);
            })->values();
        }
    @endphp

    <style>
        /* ===== Layout del formulario ===== */
        .form-3{
            display:grid;
            grid-template-columns:repeat(3,minmax(220px,1fr));
            gap:18px 24px;
            margin-top:8px;
        }
        @media (max-width:1024px){ .form-3{ grid-template-columns:repeat(2,minmax(220px,1fr)); } }
        @media (max-width:640px){ .form-3{ grid-template-columns:1fr; } }

        .pair{ display:flex; flex-direction:column; gap:6px; align-items:center; }
        .pair label{ font-weight:600; text-align:center; color:#111827; }

        /* Controles coherentes con el tema */
        .control{
            width:100%; max-width:280px;
            padding:10px 12px;
            border:1px solid var(--border);
            border-radius:10px; background:#fff; color:var(--text);
            transition:border-color .15s, box-shadow .15s;
        }
        .control:focus{
            border-color:#c7d2fe;
            box-shadow:0 0 0 4px rgba(59,130,246,.12);
            outline:0;
        }

        /* DV pequeño y centrado */
        .control--dv{ max-width:110px; text-align:center; }

        .actions{ grid-column:1 / -1; display:flex; justify-content:center; margin-top:6px; }

        h3{ text-align:center; margin:0; }

        /* ===== Secciones (fieldsets visuales) ===== */
        .section {
            grid-column: 1 / -1;
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:14px 16px;
        }
        .section h4{
            margin:0 0 10px 0;
            text-align:center;
            color:#0f172a;
        }
        .section-grid{
            display:grid;
            grid-template-columns:repeat(3,minmax(220px,1fr));
            gap:14px 18px;
        }
        @media (max-width:1024px){ .section-grid{ grid-template-columns:repeat(2,minmax(220px,1fr)); } }
        @media (max-width:640px){ .section-grid{ grid-template-columns:1fr; } }

        /* ===== Tabla ===== */
        .table-wrap{ width:100%; overflow:auto; }
        table{ width:100%; border-collapse:collapse; background:#fff; }
        thead th{
            background:#f8fafc; color:#475569; font-weight:700;
            text-transform:uppercase; letter-spacing:.02em; font-size:.9rem;
            border-bottom:1px solid #e5e7eb;
        }
        th,td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; }
        tbody tr:nth-child(even){ background:#fcfdff; }
        table thead th, table tbody td{ text-align:center; }
        td:nth-child(3), td:nth-child(5), td:nth-child(6), td:nth-child(7){ text-align:left; } /* Nombre/Emails a la izq */

        .muted{ color:#64748B; text-align:center; }
    </style>

    <div class="card">
        <h3>Nuevo proveedor</h3><br>
        <form method="POST" action="{{ route('admin.proveedores.store') }}" class="form-3">
            @csrf

            <div class="pair">
                <label for="tipo">Tipo usuario:</label>
                <select id="tipo" name="tipo" class="control">
                    <option value="empresa">Empresa</option>
                    <option value="persona">Persona</option>
                </select>
            </div>

            <div class="pair">
                <label for="rut_base">RUT base:</label>
                <input id="rut_base" name="rut_base" type="number" required class="control"
                       placeholder="Ej: 76451230" autocomplete="off">
            </div>

            <div class="pair">
                <label for="rut_dv">Dígito verificador:</label>
                <input id="rut_dv" name="rut_dv" maxlength="1" required class="control control--dv"
                       placeholder="K/0-9" autocomplete="off">
            </div>

            <div class="pair">
                <label for="nombre">Nombre:</label>
                <input id="nombre" name="nombre" required class="control" placeholder="Proveedor Spa o Persona">
            </div>

            <div class="pair">
                <label for="giro">Giro:</label>
                <input id="giro" name="giro" class="control" placeholder="Aseo y Mantención, etc.">
            </div>

            <div class="pair">
                <label for="email">Correo:</label>
                <input id="email" name="email" type="email" class="control" placeholder="ventas@proveedor.cl" autocomplete="email">
            </div>

            <div class="pair">
                <label for="telefono">Teléfono:</label>
                <input id="telefono" name="telefono" class="control" placeholder="+56 9 1234 5678" autocomplete="tel">
            </div>

            {{-- ===== Contacto de la empresa ===== --}}
            <div class="section">
                <h4>Contacto de la empresa</h4>
                <div class="section-grid">
                    <div class="pair">
                        <label for="contacto_nombre">Nombre contacto</label>
                        <input id="contacto_nombre" name="contacto_nombre" class="control" placeholder="Ej: María Pérez">
                    </div>
                    <div class="pair">
                        <label for="contacto_email">Correo contacto</label>
                        <input id="contacto_email" name="contacto_email" type="email" class="control" placeholder="contacto@proveedor.cl">
                    </div>
                    <div class="pair">
                        <label for="contacto_telefono">Teléfono contacto</label>
                        <input id="contacto_telefono" name="contacto_telefono" class="control" placeholder="+56 2 2345 6789">
                    </div>
                </div>
            </div>

            {{-- ===== Persona que te atiende (ejecutivo) ===== --}}
            <div class="section">
                <h4>Persona que te atiende</h4>
                <div class="section-grid">
                    <div class="pair">
                        <label for="ejecutivo_nombre">Nombre</label>
                        <input id="ejecutivo_nombre" name="ejecutivo_nombre" class="control" placeholder="Ej: Juan Soto">
                    </div>
                    <div class="pair">
                        <label for="ejecutivo_email">Correo</label>
                        <input id="ejecutivo_email" name="ejecutivo_email" type="email" class="control" placeholder="juan.soto@proveedor.cl">
                    </div>
                    <div class="pair">
                        <label for="ejecutivo_telefono">Teléfono</label>
                        <input id="ejecutivo_telefono" name="ejecutivo_telefono" class="control" placeholder="+56 9 8765 4321">
                    </div>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn--sm">Guardar</button>
            </div>
        </form><br>
    </div>

    <div class="card">
        <h3>Listado</h3><br>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>RUT</th>
                        <th>Nombre</th>
                        <th>Giro</th>
                        <th>Email</th>
                        <th>Contacto empresa</th>
                        <th>Ejecutivo que atiende</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($proveedoresCol as $p)
                        @php
                            $id     = is_array($p) ? ($p['id_proveedor'] ?? null) : ($p->id_proveedor ?? null);
                            $rut_b  = is_array($p) ? ($p['rut_base'] ?? '') : ($p->rut_base ?? '');
                            $rut_dv = strtoupper(is_array($p) ? ($p['rut_dv'] ?? '') : ($p->rut_dv ?? ''));
                            $nombre = is_array($p) ? ($p['nombre'] ?? '') : ($p->nombre ?? '');
                            $giro   = is_array($p) ? ($p['giro'] ?? '') : ($p->giro ?? '');
                            $email  = is_array($p) ? ($p['email'] ?? '') : ($p->email ?? '');

                            $ceNombre = is_array($p) ? ($p['contacto_nombre'] ?? null) : ($p->contacto_nombre ?? null);
                            $ceFono   = is_array($p) ? ($p['contacto_telefono'] ?? null) : ($p->contacto_telefono ?? null);
                            $ceMail   = is_array($p) ? ($p['contacto_email'] ?? null) : ($p->contacto_email ?? null);

                            $ejNombre = is_array($p) ? ($p['ejecutivo_nombre'] ?? null) : ($p->ejecutivo_nombre ?? null);
                            $ejFono   = is_array($p) ? ($p['ejecutivo_telefono'] ?? null) : ($p->ejecutivo_telefono ?? null);
                            $ejMail   = is_array($p) ? ($p['ejecutivo_email'] ?? null) : ($p->ejecutivo_email ?? null);
                        @endphp
                        <tr>
                            <td>{{ $id }}</td>
                            <td>{{ $rut_b }}-{{ $rut_dv }}</td>
                            <td>{{ $nombre }}</td>
                            <td>{{ $giro }}</td>
                            <td>{{ $email }}</td>

                            {{-- Contacto de la empresa --}}
                            <td>
                                @if($ceNombre || $ceFono || $ceMail)
                                    <div><strong>{{ $ceNombre ?? '-' }}</strong></div>
                                    <div>{{ $ceMail ?? '-' }}</div>
                                    <div>{{ $ceFono ?? '-' }}</div>
                                @else
                                    -
                                @endif
                            </td>

                            {{-- Ejecutivo que atiende --}}
                            <td>
                                @if($ejNombre || $ejFono || $ejMail)
                                    <div><strong>{{ $ejNombre ?? '-' }}</strong></div>
                                    <div>{{ $ejMail ?? '-' }}</div>
                                    <div>{{ $ejFono ?? '-' }}</div>
                                @else
                                    -
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
        </div><br>
    </div>
@endsection
