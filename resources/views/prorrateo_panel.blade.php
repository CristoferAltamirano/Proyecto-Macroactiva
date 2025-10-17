@extends('layout')
@section('title', 'Prorrateo')

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

        // Asegurar colecciones y filtrar en la vista
        $condosCol = collect($condos ?? []);
        $reglasCol = collect($reglas ?? []);

        if (!$isSA && $ctxId > 0) {
            $condosCol = $condosCol->filter(function ($c) use ($ctxId) {
                $id = is_array($c) ? ($c['id_condominio'] ?? null) : ($c->id_condominio ?? null);
                return (int) $id === $ctxId;
            })->values();

            $reglasCol = $reglasCol->filter(function ($r) use ($ctxId) {
                $rid = is_array($r) ? ($r['id_condominio'] ?? null) : ($r->id_condominio ?? null);
                return (int) $rid === $ctxId;
            })->values();
        }
    @endphp

    <style>
        /* ====== Grid 3 columnas ====== */
        .form-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(260px, 1fr));
            gap: 18px 24px;
            margin-top: 8px;
            justify-items: center;
        }
        .pair { display:flex; flex-direction:column; gap:6px; align-items:center; width:100%; }
        .pair label { font-weight:600; text-align:center; }
        .control {
            width:100%; max-width:280px; padding:6px 8px;
            border:1px solid #e5e7eb; border-radius:10px; background:#fff; color:#0f172a;
            outline:0; transition:border-color .15s, box-shadow .15s; text-align:center; box-shadow: var(--shadow-xs);
        }
        select.control { text-align-last:center; }
        .control:focus { border-color:#c7d2fe; box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        .row-2 { grid-column:1 / -1; display:flex; justify-content:center; gap:24px; flex-wrap:wrap; align-items:flex-end; }
        .row-2 .pair { width:auto; }
        .row-2 .pair .control { max-width:320px; }
        .actions { grid-column:1 / -1; display:flex; justify-content:center; margin-top:8px; }
        h3 { text-align:center; }
        .sub { text-align:center; color:#475569; margin-top:10px; font-size:1rem; }
        table thead th, table tbody td { text-align:center; }
        .inline-form { display:flex; gap:8px; justify-content:center; align-items:center; flex-wrap:wrap; }
        .table-wrap { width:100%; overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow: var(--shadow-xs); }
        .table-flat { width:100%; border-collapse:collapse; }
        .table-flat thead th {
            position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569; font-weight:700;
            text-transform:uppercase; letter-spacing:.02em; font-size:.9rem; border-bottom:1px solid #e5e7eb;
        }
        .table-flat th, .table-flat td { padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#111827; }
        .table-flat tbody tr:hover { background:#f9fafb; }
        @media (max-width:1024px){ .form-3{ grid-template-columns: repeat(2, minmax(260px, 1fr)); } }
        @media (max-width:640px){
            .form-3{ grid-template-columns:1fr; }
            .row-2{ flex-direction:column; align-items:center; }
            .row-2 .pair .control{ max-width:100%; }
            .table-flat th, .table-flat td{ padding:10px 12px; }
        }
    </style>

    <div class="card">
        <h3>Nueva regla</h3>
        <p class="sub">Este formulario se utiliza para crear nuevas reglas de prorrateo para distribuir los gastos entre los condominios según diferentes criterios.</p><br>
        <form method="POST" action="{{ route('admin.prorrateo.store') }}" class="form-3">
            @csrf

            <div class="pair">
                <label for="id_condominio">Seleccione condominio:</label>
                <select id="id_condominio" name="id_condominio" required class="control">
                    @forelse ($condosCol as $c)
                        <option value="{{ is_array($c) ? $c['id_condominio'] : $c->id_condominio }}">
                            {{ is_array($c) ? ($c['nombre'] ?? ('#'.$c['id_condominio'])) : ($c->nombre ?? ('#'.$c->id_condominio)) }}
                        </option>
                    @empty
                        <option value="" disabled selected>(sin condominio)</option>
                    @endforelse
                </select>
            </div>

            <div class="pair">
                <label for="id_concepto_cargo">Tipo Grupo:</label>
                <select id="id_concepto_cargo" name="id_concepto_cargo" required class="control">
                    @foreach (($conceptos ?? collect()) as $c)
                        <option value="{{ is_array($c) ? $c['id_concepto_cargo'] : $c->id_concepto_cargo }}">
                            {{ is_array($c) ? ($c['nombre'] ?? ('#'.$c['id_concepto_cargo'])) : ($c->nombre ?? ('#'.$c->id_concepto_cargo)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pair">
                <label for="tipo">Clasificación:</label>
                <select id="tipo" name="tipo" required class="control">
                    <option value="ordinario">Ordinario</option>
                    <option value="extra">Extra</option>
                    <option value="especial">Especial</option>
                </select>
            </div>

            <div class="pair">
                <label for="criterio">Criterio:</label>
                <select id="criterio" name="criterio" required class="control">
                    <option value="coef_prop">Coef. propiedad</option>
                    <option value="por_m2">Por m²</option>
                    <option value="igualitario">Igualitario</option>
                    <option value="por_tipo">Por tipo (pesos)</option>
                    <option value="monto_fijo">Monto fijo</option>
                </select>
            </div>

            <div class="pair">
                <label for="monto_total">Monto total / fijo:</label>
                <input id="monto_total" name="monto_total" type="number" step="0.01" class="control" inputmode="decimal">
            </div>

            <div class="pair">
                <label for="peso_vivienda">Peso vivienda:</label>
                <input id="peso_vivienda" name="peso_vivienda" type="number" step="0.000001" class="control" inputmode="decimal">
            </div>

            <div class="pair">
                <label for="peso_bodega">Peso bodega:</label>
                <input id="peso_bodega" name="peso_bodega" type="number" step="0.000001" class="control" inputmode="decimal">
            </div>

            <div class="pair">
                <label for="peso_estacionamiento">Peso estacionamiento:</label>
                <input id="peso_estacionamiento" name="peso_estacionamiento" type="number" step="0.000001" class="control" inputmode="decimal">
            </div>

            <div class="pair">
                <label for="vigente_desde">Vigente desde:</label>
                <input id="vigente_desde" name="vigente_desde" type="date" required class="control">
            </div>

            <div class="row-2">
                <div class="pair">
                    <label for="vigente_hasta">Vigente hasta:</label>
                    <input id="vigente_hasta" name="vigente_hasta" type="date" class="control">
                </div>
                <div class="pair">
                    <label for="descripcion">Descripción:</label>
                    <input id="descripcion" name="descripcion" class="control">
                </div>
            </div>

            <div class="actions">
                <button class="btn">Crear</button>
            </div>
        </form><br>
    </div>

    <div class="card">
        <h3>Reglas activas</h3><br>
        <div class="table-wrap">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th>Tipo Grupo</th>
                        <th>Clasificación</th>
                        <th>Criterio</th>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th>Generar cargos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reglasCol as $r)
                        @php
                            $rid = is_array($r) ? ($r['id_prorrateo'] ?? null) : ($r->id_prorrateo ?? null);
                        @endphp
                        <tr>
                            <td>{{ is_array($r) ? ($r['condominio'] ?? '') : ($r->condominio ?? '') }}</td>
                            <td>{{ is_array($r) ? ($r['concepto'] ?? '') : ($r->concepto ?? '') }}</td>
                            <td>{{ is_array($r) ? ($r['tipo'] ?? '') : ($r->tipo ?? '') }}</td>
                            <td>{{ is_array($r) ? ($r['criterio'] ?? '') : ($r->criterio ?? '') }}</td>
                            <td>{{ is_array($r) ? ($r['vigente_desde'] ?? '') : ($r->vigente_desde ?? '') }}</td>
                            <td>{{ is_array($r) ? ($r['vigente_hasta'] ?? '') : ($r->vigente_hasta ?? '') }}</td>
                            <td>
                                @if($rid)
                                    <form method="POST" action="{{ route('admin.prorrateo.generar', ['id' => $rid]) }}" class="inline-form">
                                        @csrf
                                        <input name="periodo" placeholder="AAAAMM" pattern="[0-9]{6}" title="Formato: 6 dígitos (AAAAMM)" required class="control" style="max-width:140px">
                                        <button class="btn">Generar</button>
                                    </form>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">Sin reglas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div><br>
    </div>
@endsection
