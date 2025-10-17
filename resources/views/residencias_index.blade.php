@extends('layout')
@section('title', 'Residentes')

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
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Schema;

        // Preferimos la nueva colección con elegibles; si no existe, caemos a $usuarios.
        $elegibles = ($usuariosElegibles ?? null) ?: ($usuarios ?? collect());
        $unidades  = $unidades ?? collect();
        $ult       = $ult       ?? collect();

        /* ================= Roles/alcance ================= */
        $user = auth()->user();
        $hasRole = function($user, $role) {
            if (method_exists($user, 'hasRole')) return $user->hasRole($role);
            $raw = $user->role ?? $user->rol ?? $user->tipo ?? $user->tipo_usuario ?? null;
            return is_string($raw) && mb_strtolower($raw) === mb_strtolower($role);
        };
        $esSuper = $hasRole($user, 'super_admin');

        $normIds = function(array $ids){
            $out=[]; foreach($ids as $v){ if($v!==null && $v!=='') $out[]=(int)$v; }
            return array_values(array_unique($out));
        };

        // Descubre condos permitidos (relación, columnas en users, pivots, sesión)
        $allowed = [];
        foreach (['condominios','condominio','condos','adminCondominios','condominiosAsignados','condominiosPermitidos','condominios_admin'] as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    $pl = $user->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $user->{$rel}()->pluck('condominio_id');
                    $allowed = array_merge($allowed, $pl->all());
                } catch (\Throwable $e) {}
            }
        }
        foreach (['id_condominio','condominio_id','id_condo','cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) { $allowed[] = $user->{$col}; break; }
        }
        $ctxId = (int) (session('ctx.id_condominio') ?? session('ctx_id_condominio') ?? session('ctx_condo_id') ?? session('id_condominio') ?? 0);
        if ($ctxId>0) $allowed[] = $ctxId;

        // pivots comunes
        $pivots = ['admin_user_condominio','admin_user_condo','user_condominio','condominio_user','asignacion_admin_condo','admin_condo_user'];
        $userId = method_exists($user,'getKey') ? $user->getKey() : ($user->id ?? null);
        foreach ($pivots as $t) {
            if (!$userId || !Schema::hasTable($t)) continue;
            $uCol=null; foreach(['user_id','id_user','usuario_id','id_usuario'] as $c) if (Schema::hasColumn($t,$c)) { $uCol=$c; break; }
            $cCol=null; foreach(['id_condominio','condominio_id','id_condo','cod_condominio'] as $c) if (Schema::hasColumn($t,$c)) { $cCol=$c; break; }
            if (!$uCol || !$cCol) continue;
            try { $allowed = array_merge($allowed, DB::table($t)->where($uCol,$userId)->pluck($cCol)->all()); } catch (\Throwable $e) {}
        }
        $allowed = $normIds($allowed);

        // Señales de UI
        $sinCondominios = (!$esSuper && empty($allowed));

        /* ================= Mapa id_unidad -> id_condominio ================= */
        $unidadCondoMap = [];

        // 1) si $unidades ya trae id_condominio/condominio_id lo mapeamos
        if ($unidades instanceof \Illuminate\Support\Collection) {
            foreach ($unidades as $u) {
                $cid = $u->id_condominio ?? $u->condominio_id ?? null;
                if ($cid !== null && isset($u->id_unidad)) {
                    $unidadCondoMap[(int)$u->id_unidad] = (int)$cid;
                }
            }
        }

        // Unidades usadas en $ult (por si no vinieron en $unidades)
        $unitIdsFromUlt = [];
        if ($ult instanceof \Illuminate\Support\Collection) {
            foreach ($ult as $r) {
                if (isset($r->id_unidad)) $unitIdsFromUlt[] = (int)$r->id_unidad;
            }
        }

        // 2) Cargamos faltantes de forma segura:
        //    - si unidad tiene id_condominio/condominio_id lo usamos
        //    - si no, LEFT JOIN grupo para obtener g.id_condominio
        $needIds = [];
        foreach (array_unique($unitIdsFromUlt) as $uid) {
            if (!array_key_exists($uid, $unidadCondoMap)) $needIds[] = $uid;
        }
        if (!empty($needIds) && Schema::hasTable('unidad')) {
            $cidCol = null;
            foreach (['id_condominio','condominio_id','id_condo'] as $c) {
                if (Schema::hasColumn('unidad', $c)) { $cidCol = $c; break; }
            }

            if ($cidCol) {
                // Caso 1: la columna existe en unidad
                $extra = DB::table('unidad')
                    ->whereIn('id_unidad', $needIds)
                    ->pluck($cidCol, 'id_unidad');
            } elseif (Schema::hasTable('grupo') && Schema::hasColumn('grupo','id_condominio') && Schema::hasColumn('unidad','id_grupo')) {
                // Caso 2: obtenerlo desde grupo.id_condominio
                $extra = DB::table('unidad as u')
                    ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                    ->whereIn('u.id_unidad', $needIds)
                    ->pluck('g.id_condominio', 'u.id_unidad');
            } else {
                $extra = collect(); // no hay forma de inferir
            }

            foreach ($extra as $uid => $cid) {
                if ($cid !== null) $unidadCondoMap[(int)$uid] = (int)$cid;
            }
        }

        /* ================= Filtro anti info cruzada ================= */
        if (!$esSuper) {
            if (empty($allowed)) {
                // sin alcance => no mostrar nada sensible
                $unidades = collect();
                $ult      = collect();
                // $elegibles: se deja como indicador global
            } else {
                // filtra unidades por condo
                if ($unidades instanceof \Illuminate\Support\Collection && $unidades->isNotEmpty()) {
                    $unidades = $unidades->filter(function($u) use ($allowed, $unidadCondoMap) {
                        // si viene id_condominio directo úsalo; si no, intenta con el mapa
                        $cid = $u->id_condominio ?? $u->condominio_id ?? ($unidadCondoMap[(int)($u->id_unidad ?? 0)] ?? null);
                        return $cid !== null ? in_array((int)$cid, $allowed, true) : false;
                    })->values();
                }

                // filtra residencias recientes ($ult) por condo: por id_condominio o via id_unidad -> mapa
                if ($ult instanceof \Illuminate\Support\Collection && $ult->isNotEmpty()) {
                    $ult = $ult->filter(function($r) use ($allowed, $unidadCondoMap) {
                        if (property_exists($r,'id_condominio') && $r->id_condominio !== null) {
                            return in_array((int)$r->id_condominio, $allowed, true);
                        }
                        if (isset($r->id_unidad) && isset($unidadCondoMap[(int)$r->id_unidad])) {
                            return in_array((int)$unidadCondoMap[(int)$r->id_unidad], $allowed, true);
                        }
                        // si no podemos determinar condominio, mejor ocultar (fail-safe)
                        return false;
                    })->values();
                }
            }
        }

        // Deshabilitar el guardado si no hay unidades visibles para este usuario
        $noHayUnidadesParaMi = ($unidades instanceof \Illuminate\Support\Collection) ? $unidades->isEmpty() : empty($unidades);
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

        @media (min-width:720px) {
            .form-grid-2 { grid-template-columns: 1fr 1fr; }
        }

        .col-span-2 { grid-column: 1 / -1; }

        .form-field {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .form-field label { font-weight: 600; text-align: center; }

        /* Controles compactos y centrados */
        .control {
            width: 100%;
            max-width: 340px;
            padding: 6px 8px;
            font-size: .92rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-xs);
            outline: none;
            text-align: center;
            margin: 0 auto;
            display: block;
        }

        select.control { text-align-last: center; }

        .control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .center { display: flex; justify-content: center; align-items: center; }

        h3, label, tr, tr th { text-align: center; }

        /* Tabla con contenedor y encabezado fijo */
        .table-wrap {
            width: 100%;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--card);
            box-shadow: var(--shadow-xs);
        }

        .table-flat { width: 100%; border-collapse: collapse; }

        .table-flat thead th,
        .table-flat tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            text-align: center;
        }

        .table-flat thead th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            z-index: 1;
        }

        .table-flat tbody tr:hover { background: #f9fafb; }

        /* Form inline (terminar) */
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
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .muted { color:#6b7280; }
        .badge { display:inline-block;padding:.15rem .5rem;border-radius:.5rem;font-size:.75rem; }
        .badge-warn{ background:#fff1f2;color:#9f1239;border:1px solid #fecdd3; }
        .alert { padding:12px 14px; background:#FEF3C7; border:1px solid #FDE68A; border-radius:10px; color:#92400E; margin-bottom:10px; }
    </style>

    <div class="card">
        <h3>Nueva residencia</h3>

        @if ($sinCondominios && !$esSuper)
            <div class="alert">Tu usuario no tiene condominios asignados. Pide a un <strong>super_admin</strong> que te asigne uno para poder registrar residencias.</div>
        @endif

        {{-- Hint cuando no hay elegibles --}}
        @if(($elegibles instanceof \Illuminate\Support\Collection ? $elegibles->isEmpty() : empty($elegibles)))
            <div class="muted" style="margin-top:6px">
                <span class="badge badge-warn">No hay usuarios elegibles</span>
                Crea primero al residente/copropietario en <strong>Usuarios</strong> o verifica que no tenga una residencia vigente.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.residencias.store') }}" class="form-grid-2">
            @csrf

            <div class="form-field">
                <label for="id_unidad">Número Unidad:</label>
                <select id="id_unidad" name="id_unidad" required class="control" @if($noHayUnidadesParaMi && !$esSuper) disabled @endif>
                    <option value="">Selecciona una unidad…</option>
                    @foreach ($unidades as $u)
                        <option value="{{ $u->id_unidad }}" @selected(old('id_unidad') == $u->id_unidad)>
                            #{{ $u->id_unidad }} {{ $u->codigo ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="id_usuario">Usuario:</label>
                <select id="id_usuario" name="id_usuario" required class="control"
                        {{ (empty($elegibles) || (is_object($elegibles) && method_exists($elegibles,'isEmpty') && $elegibles->isEmpty())) ? 'disabled' : '' }}>
                    <option value="">Selecciona un usuario…</option>
                    @foreach ($elegibles as $u)
                        @php
                            $uid  = $u->id_usuario ?? null;
                            $name = trim(($u->nombres ?? '').' '.($u->apellidos ?? ''));
                            $mail = $u->email ?? '';
                        @endphp
                        <option value="{{ $uid }}" @selected(old('id_usuario') == $uid)>
                            {{ $name }} ({{ $mail }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="origen">Tipo usuario:</label>
                <select id="origen" name="origen" class="control">
                    <option value="propietario"   @selected(old('origen')==='propietario')>Propietario</option>
                    <option value="arrendatario"  @selected(old('origen','arrendatario')==='arrendatario')>Arrendatario</option>
                </select>
            </div>

            <div class="form-field">
                <label for="desde">Fecha:</label>
                <input id="desde" type="date" name="desde" required class="control"
                       value="{{ old('desde', \Carbon\Carbon::today()->toDateString()) }}">
            </div>

            <div class="form-field col-span-2">
                <label for="observacion">Observación:</label>
                <input id="observacion" type="text" name="observacion"
                       placeholder="Observación (opcional)" class="control"
                       value="{{ old('observacion') }}">
            </div>

            <!-- Botón centrado ocupando la fila completa -->
            <div class="col-span-2 center" style="margin-top:6px;">
                <button class="btn" @if(($noHayUnidadesParaMi && !$esSuper) || (is_object($elegibles) && method_exists($elegibles,'isEmpty') && $elegibles->isEmpty())) disabled @endif>
                    Guardar
                </button>
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
                        <th>Residente</th>
                        <th>Origen</th>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th>Terminar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ult as $r)
                        @php
                            $nombre = trim(($r->nombres ?? '').' '.($r->apellidos ?? ''));
                        @endphp
                        <tr id="res{{ $r->id_residencia }}">
                            <td>{{ $r->id_residencia }}</td>
                            <td>{{ $r->unidad ?? $r->id_unidad }}</td>
                            <td>{{ $nombre }}</td>
                            <td>{{ $r->origen }}</td>
                            <td>{{ $r->desde }}</td>
                            <td>{{ $r->hasta ?? 'Vigente' }}</td>
                            <td>
                                @if (!$r->hasta)
                                    <form method="POST"
                                          action="{{ route('admin.residencias.terminar', $r->id_residencia) }}"
                                          class="inline-form">
                                        @csrf
                                        <input type="date" name="hasta" required value="{{ \Carbon\Carbon::today()->toDateString() }}">
                                        <button class="btn">Terminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">
                                @if($sinCondominios && !$esSuper)
                                    No puedes ver residencias porque no tienes condominios asignados.
                                @else
                                    Sin datos.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
