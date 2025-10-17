@extends('layout')
@section('title', 'Unidades')

@section('content')
    @include('partials.flash')

    @php
        // ====== Rol actual ======
        $role = optional(auth()->user())->rol ?? optional(auth()->user())->tipo_usuario ?? null;
        $isSuper = $role === 'super_admin';

        // Fallbacks/tolerantes a lo que envíe el controller
        $tipos     = $tipos     ?? [];
        $segmentos = $segmentos ?? ($segs ?? []);
        $subtipos  = $subtipos  ?? ($subs ?? []); // ya no se usa en el form (por cambio a "estado")
        $condos    = $condos    ?? collect();
        $idCondo   = isset($idCondo) ? $idCondo : request('id_condominio');

        // --- UNION FORZADA: default + lo que venga
        $tiposUnidadDefaults = ['Vivienda', 'Departamento', 'Local', 'Bodega', 'Estacionamiento'];
        $tiposUnidadFromData = collect($tipos)
            ->map(fn($t) => is_object($t) ? ($t->nombre ?? ($t->texto ?? (string)$t)) : (string)$t)
            ->filter()
            ->values()
            ->all();
        $tiposUnidad = array_values(array_unique(array_merge($tiposUnidadDefaults, $tiposUnidadFromData)));

        // Para admins (no super_admin): si solo hay 1 condominio, lo mostramos como activo
        $onlyOneCondoForAdmin = !$isSuper && $condos->count() === 1;

        // Nombre del condominio activo (para el encabezado bonito)
        $activeCondoName = null;
        if ($onlyOneCondoForAdmin) {
            $activeCondoName = optional($condos->first())->nombre;
        } elseif ($isSuper && $idCondo) {
            $activeCondoName = optional($condos->firstWhere('id_condominio', (int)$idCondo))->nombre;
        }
    @endphp

    <style>
        /* ===== Grid 1 → 2 → 3 columnas ===== */
        .form-grid-3 {
            display: grid;
            gap: 14px 22px;
            grid-template-columns: 1fr;
            max-width: 1100px;
            margin: 0 auto;
        }
        @media (min-width: 720px) { .form-grid-3 { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 1100px) { .form-grid-3 { grid-template-columns: 1fr 1fr 1fr; } }

        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-weight: 600; text-align: center; }
        .field label:not(.no-colon)::after { content: ':'; }

        .control {
            width: 100%; padding: 6px 8px; font-size: .92rem;
            border: 1px solid var(--border); border-radius: 10px; text-align: center;
            box-shadow: var(--shadow-xs); outline: none;
        }
        select.control { text-align-last: center; }
        .control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(6, 182, 212, .15); }

        .center { display: flex; justify-content: center; align-items: center; }
        .full { grid-column: 1 / -1; }

        .checks-row { display: flex; justify-content: center; align-items: center; gap: 14px; flex-wrap: wrap; width: 100%; }
        .checks-row label::after { content: ''; }
        .checks-row label { display: inline-flex; align-items: center; gap: 6px; text-align: left; }

        .table-wrap { width: 100%; overflow: auto; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); box-shadow: var(--shadow-xs); }
        .table-flat { width: 100%; border-collapse: collapse; }
        .table-flat thead th, .table-flat tbody td { text-align: center; padding: 10px 12px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table-flat thead th { position: sticky; top: 0; z-index: 1; background: #f8fafc; }
        .table-flat tbody tr:hover { background: #f9fafb; }

        @media (max-width:980px) { .form-grid-3 { grid-template-columns: 1fr; } }
    </style>

    {{-- ================== CONDOMINIO ACTIVO / SELECTOR ================== --}}
    <div class="card">
        @if(!$isSuper)
            {{-- Admin de condominio: solo muestra el condominio activo, sin selector --}}
            <h3 class="center" style="margin-top:4px">Condominio activo</h3>
            <div class="center" style="margin-top:8px">
                @if($activeCondoName)
                    <span class="pill" title="Condominio activo">{{ $activeCondoName }}</span>
                @else
                    <span class="muted">Sin condominio asignado</span>
                @endif
            </div>
        @else
            {{-- super_admin: mantiene selector con (Todos) --}}
            <h3 class="center" style="margin-top:4px">Filtrar por condominio</h3>
            <form method="GET" action="{{ route('admin.unidades.panel') }}" class="center" style="gap:12px;flex-wrap:wrap">
                <div class="field">
                    <label for="id_condominio">Seleccione un condominio</label>
                    <select name="id_condominio" id="id_condominio" class="control" style="max-width:360px">
                        <option value="">(Todos)</option>
                        @foreach ($condos as $c)
                            <option value="{{ $c->id_condominio }}"
                                {{ isset($idCondo) && (int)$idCondo === (int)$c->id_condominio ? 'selected' : '' }}>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn">Ver</button>
            </form>
        @endif
    </div>

    {{-- ================== FORM NUEVA/EDITAR UNIDAD ================== --}}
    <div class="card">
        <h3 class="center" style="margin-top:4px">Nueva unidad</h3>

        <form method="POST" action="{{ route('admin.unidades.store') }}" id="frmUnidad" class="form-grid-3">
            @csrf
            <input type="hidden" name="id_unidad" id="id_unidad">

            {{-- Grupo --}}
            <div class="field">
                <label for="id_grupo">Selecciona grupo</label>
                <select id="id_grupo" name="id_grupo" class="control" required>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id_grupo }}">{{ $g->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Código --}}
            <div class="field">
                <label for="codigo">Código</label>
                <input id="codigo" name="codigo" class="control" value="{{ old('codigo', '') }}">
            </div>

            {{-- Dirección --}}
            <div class="field">
                <label for="direccion">Dirección</label>
                <input id="direccion" name="direccion" class="control" value="{{ old('direccion', '') }}">
            </div>

            {{-- Tipo de grupo --}}
            <div class="field">
                <label for="tipo_grupo"><strong>Tipo de grupo</strong></label>
                @php $tg = old('tipo_grupo','Torre'); @endphp
                <select id="tipo_grupo" name="tipo_grupo" class="control" required>
                    <option value="Torre" @selected($tg === 'Torre')>Torre</option>
                    <option value="Etapa" @selected($tg === 'Etapa')>Etapa</option>
                    <option value="Loteo" @selected($tg === 'Loteo')>Loteo</option>
                </select>
            </div>

            {{-- Segmento --}}
            <div class="field">
                <label for="segmento">Segmento</label>
                <select id="segmento" name="segmento" class="control">
                    @foreach ($segmentos as $seg)
                        @php
                            $sText = is_object($seg) ? ($seg->nombre ?? ($seg->texto ?? '')) : (string)$seg;
                            $sValue = is_object($seg) ? ($seg->nombre ?? ($seg->id_segmento ?? ($seg->id ?? $sText))) : (string)$seg;
                        @endphp
                        <option value="{{ $sValue }}" {{ old('segmento') === $sValue ? 'selected' : '' }}>
                            {{ $sText }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tipo de unidad --}}
            <div class="field">
                <label for="tipo_unidad"><strong>Tipo de unidad</strong></label>
                @php $tu = old('tipo_unidad','Vivienda'); @endphp
                <select id="tipo_unidad" name="tipo_unidad" class="control" required>
                    @foreach ($tiposUnidad as $txt)
                        <option value="{{ $txt }}" @selected($tu === $txt)>{{ $txt }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Rol SII --}}
            <div class="field">
                <label for="rol_sii">Rol SII</label>
                <input id="rol_sii" name="rol_sii" class="control" value="{{ old('rol_sii', '') }}">
            </div>

            {{-- Medidas m2 --}}
            <div class="field">
                <label for="m2">Medidas (m²)</label>
                <input id="m2" name="m2" type="number" step="0.000001" class="control" value="{{ old('m2', '') }}">
            </div>

            {{-- Coef propiedad --}}
            <div class="field">
                <label for="coef_propiedad">Coef. propiedad</label>
                <input id="coef_propiedad" name="coef_propiedad" type="number" step="0.000001" class="control"
                       value="{{ old('coef_propiedad', '') }}">
            </div>

            {{-- Estado --}}
            <div class="field">
                <label for="estado"><strong>Estado</strong></label>
                @php $es = old('estado','activo'); @endphp
                <select id="estado" name="estado" class="control" required>
                    <option value="activo" @selected($es === 'activo')>Activo</option>
                    <option value="inactivo" @selected($es === 'inactivo')>Inactivo</option>
                </select>
            </div>

            {{-- Checks --}}
            <div class="field full">
                <div class="checks-row">
                    <label>
                        <input type="checkbox" name="anexo_incluido" {{ old('anexo_incluido') ? 'checked' : '' }}>
                        Anexo incluido
                    </label>
                    <label>
                        <input type="checkbox" name="anexo_cobrable" {{ old('anexo_cobrable') ? 'checked' : '' }}>
                        Anexo cobrable
                    </label>
                    <label>
                        <input type="checkbox" name="habitable" {{ old('habitable') ? 'checked' : '' }}>
                        Habitable
                    </label>
                </div>
            </div>

            <div class="center" style="grid-column:1/-1; gap:10px;">
                <button type="button" class="btn" onclick="resetForm()">Nuevo</button>
                <button class="btn">Guardar</button>
            </div>
        </form>
    </div>

    {{-- ================== LISTADO ================== --}}
    <div class="card">
        <h3 class="center">Listado unidades (primeros 200)</h3>
        <div class="table-wrap" style="margin-top:10px">
            <table class="table table-flat">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Grupo</th>
                        <th>Código</th>
                        <th>Tipo grupo</th>
                        <th>Tipo unidad</th>
                        <th>Estado</th>
                        <th>Segmento</th>
                        <th>m²</th>
                        <th>Coef.</th>
                        <th style="min-width:220px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unidades as $u)
                        <tr>
                            <td>{{ $u->id_unidad }}</td>
                            <td>{{ $u->grupo }}</td>
                            <td>{{ $u->codigo }}</td>
                            <td>{{ $u->tipo_grupo }}</td>
                            <td>{{ $u->tipo_unidad ?? '-' }}</td>
                            <td>{{ $u->estado ?? '-' }}</td>
                            <td>{{ $u->segmento }}</td>
                            <td>{{ $u->m2 }}</td>
                            <td>{{ $u->coef_propiedad }}</td>
                            <td>
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                                    <button class="btn" type="button" onclick='fillForm(@json($u))'>Editar</button>

                                    {{-- Admin Copropietarios (mantiene id_condominio para no romper contexto) --}}
                                    <a class="btn" target="_blank" rel="noopener"
                                       href="{{ url('/admin/copropietarios') }}?id_unidad={{ $u->id_unidad }}@if($idCondo)&id_condominio={{ (int)$idCondo }}@endif">
                                        Ad. propietario
                                    </a>

                                    {{-- Admin Residencias (mantiene id_condominio para no romper contexto) --}}
                                    <a class="btn" target="_blank" rel="noopener"
                                       href="{{ url('/admin/residencias') }}?id_unidad={{ $u->id_unidad }}@if($idCondo)&id_condominio={{ (int)$idCondo }}@endif">
                                        Ad. residente
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">Sin unidades.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function resetForm() {
            const f = document.getElementById('frmUnidad');
            f.reset();
            document.getElementById('id_unidad').value = '';
        }
        function fillForm(u) {
            document.getElementById('id_unidad').value = u.id_unidad ?? '';
            const map = {
                id_grupo: u.id_grupo,
                codigo: u.codigo ?? '',
                direccion: u.direccion ?? '',
                tipo_grupo: u.tipo_grupo ?? '',
                segmento: u.segmento ?? '',
                tipo_unidad: u.tipo_unidad ?? '',
                estado: u.estado ?? '',
                rol_sii: u.rol_sii ?? '',
                m2: u.m2 ?? '',
                coef_propiedad: u.coef_propiedad ?? ''
            };
            Object.entries(map).forEach(([k, v]) => {
                const el = document.getElementById(k);
                if (el) el.value = (v ?? '').toString();
            });
            setCheck('anexo_incluido', u.anexo_incluido == 1);
            setCheck('anexo_cobrable', u.anexo_cobrable == 1);
            setCheck('habitable', u.habitable == 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function setCheck(id, on) {
            const el = document.querySelector('input[name="' + id + '"]');
            if (el) el.checked = !!on;
        }
    </script>
@endsection
