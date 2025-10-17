@extends('layout')
@section('title', 'Maestros Condominio')
@section('content')
    @if (session('ok'))
        <div class="card"><strong>{{ session('ok') }}</strong></div>
    @endif

    @php
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Schema;

        // Fallbacks seguros
        $condos  = $condos  ?? collect();
        $seg     = $seg     ?? collect();
        $params  = $params  ?? collect();
        $reglas  = $reglas  ?? collect();

        // ======== Roles flexibles ========
        $user = auth()->user();
        $hasRole = function($user, $role) {
            if (method_exists($user, 'hasRole')) return $user->hasRole($role);
            $raw = $user->role ?? $user->rol ?? $user->tipo ?? $user->tipo_usuario ?? null;
            return is_string($raw) && mb_strtolower($raw) === mb_strtolower($role);
        };
        $esSuper = $hasRole($user, 'super_admin');

        // ======== Normalizador de IDs ========
        $normIds = function(array $ids) {
            $out = [];
            foreach ($ids as $v) {
                if ($v === null || $v === '') continue;
                $out[] = (int) $v;
            }
            return array_values(array_unique($out));
        };

        // ======== Descubrir condos permitidos ========
        $allowed = [];
        // (A) Si el controller ya envió $condos, úsalo como alcance
        if ($condos instanceof \Illuminate\Support\Collection && $condos->isNotEmpty()) {
            $allowed = array_merge($allowed, $condos->pluck('id_condominio')->all());
        }
        // (B) Relaciones típicas
        foreach (['condominios','condominio','condos','adminCondominios','condominiosAsignados','condominiosPermitidos','condominios_admin'] as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    $pl = $user->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $user->{$rel}()->pluck('condominio_id');
                    $allowed = array_merge($allowed, $pl->all());
                } catch (\Throwable $e) {}
            }
        }
        // (C) Columnas en users
        foreach (['id_condominio','condominio_id','id_condo','cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) { $allowed[] = $user->{$col}; break; }
        }
        // (D) Pivots comunes
        $pivots = ['admin_user_condominio','user_condominio','condominio_user','asignacion_admin_condo','admin_condo_user','admin_user_condo'];
        $userId = method_exists($user,'getKey') ? $user->getKey() : ($user->id ?? null);
        foreach ($pivots as $table) {
            if (!$userId || !Schema::hasTable($table)) continue;
            $userCols  = ['user_id','id_user','usuario_id','id_usuario'];
            $condoCols = ['id_condominio','condominio_id','id_condo','cod_condominio'];
            $uCol = null; foreach ($userCols as $uc) if (Schema::hasColumn($table,$uc)) { $uCol=$uc; break; }
            $cCol = null; foreach ($condoCols as $cc) if (Schema::hasColumn($table,$cc)) { $cCol=$cc; break; }
            if (!$uCol || !$cCol) continue;
            try { $allowed = array_merge($allowed, DB::table($table)->where($uCol,$userId)->pluck($cCol)->all()); } catch (\Throwable $e) {}
        }
        // (E) Contexto de sesión
        $ctxId = (int) (session('ctx.id_condominio') ?? session('ctx_id_condominio') ?? session('ctx_condo_id') ?? session('id_condominio') ?? 0);
        if ($ctxId > 0) $allowed[] = $ctxId;

        $allowed = $normIds($allowed);

        // ======== Lista final de condos para los selects ========
        if ($esSuper) {
            if (!($condos instanceof \Illuminate\Support\Collection) || $condos->isEmpty()) {
                $condos = Schema::hasTable('condominio')
                    ? DB::table('condominio')->select('id_condominio','nombre')->orderBy('nombre')->get()
                    : collect();
            }
            $condosList = $condos;
        } else {
            if (!empty($allowed)) {
                $condosList = DB::table('condominio')->select('id_condominio','nombre')
                    ->whereIn('id_condominio',$allowed)->orderBy('nombre')->get();
            } else {
                $condosList = collect();
            }
        }

        $sinCondominiosAsignados = (!$esSuper && $condosList->count() === 0);
        $tieneUnSoloCondo        = ($condosList->count() === 1);

        // ======== Preselección suave para formularios ========
        $preId = old('id_condominio') ?? request('id_condominio') ?? ($tieneUnSoloCondo ? (int)$condosList->first()->id_condominio : null);

        // ======== Anti info cruzada en tablas ($params y $reglas) ========
        $filtroColeccionPorAllowed = function($col) use ($esSuper, $allowed) {
            if (!($col instanceof \Illuminate\Support\Collection) || $col->isEmpty() || $esSuper) return $col;
            $first = $col->first();
            if (is_object($first) && property_exists($first,'id_condominio')) {
                if (empty($allowed)) return collect();
                return $col->filter(fn($r) => in_array((int)($r->id_condominio ?? 0), $allowed, true))->values();
            }
            return $col;
        };

        $params = $filtroColeccionPorAllowed($params);
        $reglas = $filtroColeccionPorAllowed($reglas);

        // ======== Segments: si traen id_condominio y hay $preId, limitamos (sin JS) ========
        if ($seg instanceof \Illuminate\Support\Collection && $seg->isNotEmpty()) {
            $firstSeg = $seg->first();
            if (is_object($firstSeg) && property_exists($firstSeg,'id_condominio') && $preId) {
                $seg = $seg->where('id_condominio', (int)$preId)->values();
            }
        }
    @endphp

    <style>
        /* ===== Cards centrados ===== */
        .card{ display:flex; flex-direction:column; align-items:center; text-align:center; }
        .card h3, .card h4{ margin-bottom:8px; }
        .alert{ padding:12px 14px; background:#FEF3C7; border:1px solid #FDE68A; border-radius:10px; color:#92400E; margin-bottom:10px; }

        /* ===== Formularios en grid (centrado real) ===== */
        .form-grid{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 300px));
            gap:10px;
            justify-content:center;
            align-items:start;
            width:100%;
            max-width:1000px;
            margin:0 auto 8px auto;
        }
        .pair{ display:flex; flex-direction:column; gap:6px; align-items:center; }
        .pair.full{ grid-column:1 / -1; }
        .pair label{ font-weight:600; }

        .control{
            width:100%; max-width:300px;
            padding:8px; border:1px solid #e5e7eb; border-radius:10px;
            margin:0 auto; text-align:center; background:#fff; color:#0f172a;
            outline:0; transition:border-color .15s, box-shadow .15s;
        }
        .control:focus{ border-color:#c7d2fe; box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        select.control{ text-align-last:center; }
        input[type="number"].control{ text-align:center; }
        .pair.full .control{ max-width:700px; }

        .actions{ display:flex; justify-content:center; width:100%; margin-top:8px; gap:8px; flex-wrap:wrap; }

        /* ===== Fila centrada para agrupar pares ===== */
        .row-center{
            grid-column:1 / -1;
            display:flex; justify-content:center; align-items:flex-start; gap:10px; flex-wrap:wrap;
        }

        /* ===== Tablas con contenedor y header fijo ===== */
        .table-wrap{
            width:100%; overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff;
        }
        table{ width:100%; border-collapse:collapse; }
        thead th{
            position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569; font-weight:700;
            text-transform:uppercase; letter-spacing:.02em; font-size:.9rem; border-bottom:1px solid #e5e7eb;
            text-align:center;
        }
        th, td{ padding:10px 12px; border-bottom:1px solid #e5e7eb; color:#111827; }
        table thead th, table tbody td{ text-align:center; }
        .num{ text-align:right !important; white-space:nowrap; }
        .muted{ color:#6b7280; text-align:center; }

        @media (max-width:640px){
            th, td{ padding:10px; }
        }
    </style>

    <div class="card">
        <h3>Parámetros de reglamento</h3><br>

        @if ($sinCondominiosAsignados && !$esSuper)
            <div class="alert">Tu usuario no tiene condominios asignados. Un <strong>super_admin</strong> debe asignarte uno para poder guardar parámetros.</div>
        @endif

        <form method="POST" action="{{ route('admin.maestros.params.save') }}">
            @csrf
            <div class="form-grid">
                <div class="pair">
                    <label for="p_condo">Condominio</label>

                    @if ($esSuper)
                        <select id="p_condo" name="id_condominio" class="control" required>
                            @forelse ($condosList as $c)
                                <option value="{{ $c->id_condominio }}" @selected((string)$preId === (string)$c->id_condominio)>
                                    {{ $c->nombre }}
                                </option>
                            @empty
                                <option value="" disabled selected>— Sin condominios —</option>
                            @endforelse
                        </select>
                    @else
                        @if ($tieneUnSoloCondo)
                            @php $c = $condosList->first(); @endphp
                            <input type="hidden" name="id_condominio" value="{{ $c->id_condominio }}">
                            <input class="control" value="{{ $c->nombre }}" disabled>
                        @else
                            <select id="p_condo" name="id_condominio" class="control" required @if($sinCondominiosAsignados) disabled @endif>
                                @forelse ($condosList as $c)
                                    <option value="{{ $c->id_condominio }}" @selected((string)$preId === (string)$c->id_condominio)>
                                        {{ $c->nombre }}
                                    </option>
                                @empty
                                    <option value="" disabled selected>— Sin condominios —</option>
                                @endforelse
                            </select>
                        @endif
                    @endif
                </div>

                <div class="pair">
                    <label for="p_fr">% Recargo Fondo Reserva</label>
                    <input id="p_fr" type="number" step="0.01" name="recargo_fondo_reserva_pct" value="5" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair">
                    <label for="p_interes">% Interés mora anual</label>
                    <input id="p_interes" type="number" step="0.001" name="interes_mora_anual_pct" class="control" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="row-center">
                    <div class="pair">
                        <label for="p_gracia">Días de gracia</label>
                        <input id="p_gracia" type="number" name="dias_gracia" value="0" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                    </div>

                    <div class="pair">
                        <label for="p_multa">Multa morosidad fija</label>
                        <input id="p_multa" type="number" step="0.01" name="multa_morosidad_fija" class="control" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button class="btn" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>Guardar</button>
            </div><br>
        </form>

        <h4 style="margin-top:14px">Configuraciones guardadas</h4>
        <div class="table-wrap" style="margin-top:8px;">
            <table>
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th class="num">Fondo reserva %</th>
                        <th class="num">Interés anual %</th>
                        <th class="num">Gracia (días)</th>
                        <th class="num">Multa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($params as $p)
                        <tr>
                            <td>{{ $p->nombre }}</td>
                            <td class="num">{{ $p->recargo_fondo_reserva_pct }}</td>
                            <td class="num">{{ $p->interes_mora_anual_pct }}</td>
                            <td class="num">{{ $p->dias_gracia }}</td>
                            <td class="num">{{ $p->multa_morosidad_fija }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div><br>
    </div>

    <div class="card">
        <h3>Reglas de interés</h3><br>

        @if ($sinCondominiosAsignados && !$esSuper)
            <div class="alert">Tu usuario no tiene condominios asignados. No puedes agregar reglas hasta tener al menos uno.</div>
        @endif

        <form method="POST" action="{{ route('admin.maestros.interes.save') }}">
            @csrf
            <div class="form-grid">
                <div class="pair">
                    <label for="r_condo">Condominio</label>

                    @if ($esSuper)
                        <select id="r_condo" name="id_condominio" class="control" required>
                            @forelse ($condosList as $c)
                                <option value="{{ $c->id_condominio }}" @selected((string)$preId === (string)$c->id_condominio)>
                                    {{ $c->nombre }}
                                </option>
                            @empty
                                <option value="" disabled selected>— Sin condominios —</option>
                            @endforelse
                        </select>
                    @else
                        @if ($tieneUnSoloCondo)
                            @php $c = $condosList->first(); @endphp
                            <input type="hidden" name="id_condominio" value="{{ $c->id_condominio }}">
                            <input class="control" value="{{ $c->nombre }}" disabled>
                        @else
                            <select id="r_condo" name="id_condominio" class="control" required @if($sinCondominiosAsignados) disabled @endif>
                                @forelse ($condosList as $c)
                                    <option value="{{ $c->id_condominio }}" @selected((string)$preId === (string)$c->id_condominio)>
                                        {{ $c->nombre }}
                                    </option>
                                @empty
                                    <option value="" disabled selected>— Sin condominios —</option>
                                @endforelse
                            </select>
                        @endif
                    @endif
                </div>

                <div class="pair">
                    <label for="r_seg">Segmento</label>
                    <select id="r_seg" name="id_segmento" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                        @forelse ($seg as $s)
                            <option value="{{ $s->id_segmento }}">{{ $s->nombre }}</option>
                        @empty
                            <option value="" disabled selected>— Sin segmentos —</option>
                        @endforelse
                    </select>
                </div>

                <div class="pair">
                    <label for="r_desde">Vigente desde</label>
                    <input id="r_desde" type="date" name="vigente_desde" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair">
                    <label for="r_hasta">Vigente hasta</label>
                    <input id="r_hasta" type="date" name="vigente_hasta" class="control" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair">
                    <label for="r_tasa">Tasa anual %</label>
                    <input id="r_tasa" type="number" step="0.001" name="tasa_anual_pct" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair">
                    <label for="r_gracia">Días de gracia</label>
                    <input id="r_gracia" type="number" name="dias_gracia" value="0" class="control" required @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair full">
                    <label for="r_fuente">Fuente URL</label>
                    <input id="r_fuente" type="url" name="fuente_url" class="control" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>

                <div class="pair full">
                    <label for="r_com">Comentario</label>
                    <input id="r_com" name="comentario" class="control" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>
                </div>
            </div>

            <div class="actions">
                <button class="btn" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>Agregar regla</button>
            </div><br>
        </form>

        <h4 style="margin-top:14px">Últimas reglas</h4>
        <div class="table-wrap" style="margin-top:8px;">
            <table>
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th>Segmento</th>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th class="num">Tasa anual %</th>
                        <th class="num">Gracia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reglas as $r)
                        <tr>
                            <td>{{ $r->condominio }}</td>
                            <td>{{ $r->segmento }}</td>
                            <td>{{ $r->vigente_desde }}</td>
                            <td>{{ $r->vigente_hasta }}</td>
                            <td class="num">{{ $r->tasa_anual_pct }}</td>
                            <td class="num">{{ $r->dias_gracia }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div><br>
    </div>
@endsection
