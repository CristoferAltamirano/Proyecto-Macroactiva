@extends('layout')
@section('title', 'Auditoría')
@section('content')
    @include('partials.flash')

    @php
        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Schema;

        // ===== Fallbacks seguros por si no vienen del controller =====
        $entidades = $entidades ?? collect();
        $entidad   = $entidad   ?? request('entidad');
        $reg       = $reg       ?? collect();
        $condos    = $condos    ?? null; // opcional: si el controller lo envía

        // ===== Usuario / Roles (tolerante) =====
        $user = auth()->user();

        $hasRoleFn = function($user, $role) {
            if (method_exists($user, 'hasRole')) return $user->hasRole($role);
            $raw = $user->role ?? $user->rol ?? $user->tipo ?? $user->tipo_usuario ?? null;
            return is_string($raw) && mb_strtolower($raw) === mb_strtolower($role);
        };

        $esSuper = $hasRoleFn($user, 'super_admin');
        $esAdminLike =
            $esSuper ||
            $hasRoleFn($user, 'admin') ||
            $hasRoleFn($user, 'administrador') ||
            $hasRoleFn($user, 'admin_condominio') ||
            $hasRoleFn($user, 'condo_admin');

        // ===== Contexto posible en sesión =====
        $ctxId  = (int) (
            session('ctx.id_condominio') ??
            session('ctx_id_condominio') ??
            session('ctx_condo_id') ??
            session('id_condominio') ??
            0
        );
        $ctxNom = session('ctx_condo_nombre') ?? session('ctx.nombre_condominio');

        // ===== Función util: normalizar IDs únicos int =====
        $normIds = function(array $ids) {
            $out = [];
            foreach ($ids as $v) {
                if ($v === null || $v === '') continue;
                $out[] = (int) $v;
            }
            return array_values(array_unique($out));
        };

        // ===== Descubrir IDs de condominios permitidos =====
        $allowed = [];

        // (A) Desde $condos entregado por el controller
        if ($condos instanceof \Illuminate\Support\Collection) {
            $allowed = array_merge($allowed, $condos->pluck('id_condominio')->all());
        }

        // (B) Relación del usuario si existe
        $relCandidates = ['condominios', 'condominio', 'condos', 'adminCondominios', 'condominiosAsignados', 'condominiosPermitidos', 'condominios_admin'];
        foreach ($relCandidates as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    $pl = $user->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $user->{$rel}()->pluck('condominio_id');
                    $allowed = array_merge($allowed, $pl->all());
                } catch (\Throwable $e) {
                    // ignora si la relación no usa esos campos
                }
            }
        }

        // (C) Columna directa en users
        foreach (['id_condominio', 'condominio_id', 'id_condo', 'cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) {
                $allowed[] = $user->{$col};
                break;
            }
        }

        // (D) Pivots comunes
        $pivotDefs = [
            'admin_user_condominio', 'user_condominio', 'condominio_user',
            'asignacion_admin_condo', 'admin_condo_user', 'admin_user_condo',
        ];
        $userId = method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? null);
        foreach ($pivotDefs as $table) {
            if (!$userId || !Schema::hasTable($table)) continue;
            $userCols  = ['user_id', 'id_user', 'usuario_id', 'id_usuario'];
            $condoCols = ['id_condominio', 'condominio_id', 'id_condo', 'cod_condominio'];
            $uCol = null; $cCol = null;
            foreach ($userCols as $uc) if (Schema::hasColumn($table, $uc)) { $uCol = $uc; break; }
            foreach ($condoCols as $cc) if (Schema::hasColumn($table, $cc)) { $cCol = $cc; break; }
            if (!$uCol || !$cCol) continue;
            try {
                $allowed = array_merge($allowed, DB::table($table)->where($uCol, $userId)->pluck($cCol)->all());
            } catch (\Throwable $e) {}
        }

        // (E) Contexto de sesión (si hay)
        if ($ctxId > 0) $allowed[] = $ctxId;

        $allowed = $normIds($allowed);

        // ===== Obtener lista de condominios para el select =====
        if ($esSuper) {
            // Super ve todos (o los entregados por el controller si venían)
            if (!($condos instanceof \Illuminate\Support\Collection)) {
                $condos = DB::table('condominio')->select('id_condominio', 'nombre')->orderBy('nombre')->get();
            }
        } else {
            // Admin-like: sólo los permitidos
            if (empty($allowed)) {
                $condos = collect(); // ninguno asignado
            } else {
                $condos = DB::table('condominio')
                    ->select('id_condominio', 'nombre')
                    ->whereIn('id_condominio', $allowed)
                    ->orderBy('nombre')
                    ->get();
            }
        }

        // ===== id_condominio solicitado por GET (normalizado/validado) =====
        $idReq = request()->filled('id_condominio') ? (int) request('id_condominio') : null;
        if (!$esSuper) {
            if ($idReq !== null && !in_array($idReq, $allowed, true)) {
                // si pidió otro, lo ignoramos
                $idReq = null;
            }
            if ($idReq === null && $condos->count() === 1) {
                $idReq = (int) $condos->first()->id_condominio;
            }
        }

        // ===== Anti info cruzada: filtra $reg por allowed cuando exista id_condominio en filas =====
        if (!$esSuper && $reg instanceof \Illuminate\Support\Collection && $reg->isNotEmpty()) {
            $first = $reg->first();
            if (is_object($first) && property_exists($first, 'id_condominio')) {
                if (empty($allowed)) {
                    $reg = collect(); // sin asignación => no muestra nada
                } else {
                    $reg = $reg->filter(fn($a) => in_array((int)($a->id_condominio ?? 0), $allowed, true))->values();
                }
            }
        }

        // ===== Señales de UI =====
        $sinCondominiosAsignados = (!$esSuper && $condos->count() === 0);
        $tieneUnSoloCondo = ($condos->count() === 1);

    @endphp

    <style>
        /* ===== Card y filtros ===== */
        .card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 8px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 16px;
            margin-bottom: 16px;
        }
        .card h3 { margin-bottom: 4px; }
        .form-inline {
            display: flex; gap: 12px; align-items: center; justify-content: center;
            flex-wrap: wrap; width: 100%;
        }
        .inline-label { font-weight: 600; }
        .control{
            padding: 8px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #0f172a;
            text-align: center; outline: 0; transition: border-color .15s, box-shadow .15s;
        }
        .control:focus{ border-color: #c7d2fe; box-shadow: 0 0 0 4px rgba(59,130,246,.12); }
        .form-inline .control{ width: auto; min-width: 240px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
            background:var(--primary); color:#fff; padding:10px 14px; border-radius:12px; cursor:pointer; text-decoration:none; box-shadow:var(--shadow-xs);
        }
        .btn:hover{ background:var(--primary-hover); box-shadow:var(--shadow-sm); }

        /* ===== Tabla principal ===== */
        .table-wrap{ width:100%; overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff; }
        table{ width:100%; border-collapse:collapse; }
        thead th{
            position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569; font-weight:700;
            text-transform:uppercase; letter-spacing:.02em; font-size:.9rem; border-bottom:1px solid #e5e7eb;
            text-align:center;
        }
        th, td{ padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#111827; }
        tbody tr:hover{ background:#f9fafb; }
        td.center, th.center{ text-align:center; }
        .muted{ color:#6b7280; text-align:center; }
        .alert {
            padding: 14px 16px; background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 10px; color: #92400E; margin-bottom: 8px;
        }

        /* ===== Mini ventana (modal) ===== */
        .modal-backdrop{
            position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center;
            z-index:50; padding:16px;
        }
        .modal-backdrop.is-open{ display:flex; }
        .modal{
            background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 25px rgba(2,6,23,.25);
            width:min(760px,96vw); max-height:80vh; overflow:auto;
        }
        .modal header{
            display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px;
            border-bottom:1px solid #e5e7eb;
        }
        .modal header h4{ margin:0; font-size:1.05rem; }
        .modal .body{ padding:12px 16px; }
        .close-btn{
            border:none; background:transparent; cursor:pointer; font-size:20px; line-height:1; padding:4px; border-radius:8px;
        }
        .close-btn:hover{ background:#f1f5f9; }

        /* ===== Contenido del modal ===== */
        .pill{ display:inline-block; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:700; font-size:.78rem; }
        .meta{ color:#64748b; font-size:.82rem; }
        .mini{
            width:100%; border-collapse:separate; border-spacing:0; margin-top:10px; border:1px solid #e5e7eb;
            border-radius:10px; overflow:hidden;
        }
        .mini th{ background:#f8fafc; font-size:.85rem; color:#475569; }
        .mini th, .mini td{ padding:8px 10px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        .mini tr:last-child td{ border-bottom:none; }
        .mini .k{ font-weight:700; color:#334155; word-break:break-word; width:180px; }
        .mini .v{ color:#0f172a; word-break:break-word; }
        .mini .arrow{ width:26px; text-align:center; color:#64748b; }
        .row-changed{ background:#fff7ed; }
        .row-same{ background:#f8fafc; color:#64748b; }

        .plain{
            background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:10px; margin-top:10px;
            white-space:pre-wrap; word-break:break-word; text-align:left;
        }

        .ctx-pill {
            background:#fff; color:var(--primary); border:1px solid #e5e7eb; padding:8px 12px; border-radius:999px;
            box-shadow:var(--shadow-xs); font-weight:600;
        }
    </style>

    <div class="card">
        <h3>Auditoría</h3>

        @if ($sinCondominiosAsignados && $esAdminLike && !$esSuper)
            <div class="alert">
                Tu usuario no tiene condominios asignados. Solicita a un <strong>super_admin</strong> que te asigne uno para ver registros de auditoría.
            </div>
        @endif

        <form method="GET" action="{{ route('admin.audit.panel') }}">
            <div class="form-inline">
                {{-- Condominio --}}
                @if ($esSuper)
                    <label for="id_condominio" class="inline-label">Condominio</label>
                    <select id="id_condominio" name="id_condominio" class="control" style="min-width:200px">
                        <option value="">(todos)</option>
                        @foreach ($condos as $co)
                            <option value="{{ $co->id_condominio }}" @selected((string)request('id_condominio') === (string)$co->id_condominio)>
                                {{ $co->nombre }}
                            </option>
                        @endforeach
                    </select>
                @else
                    @if ($tieneUnSoloCondo)
                        @php $co = $condos->first(); @endphp
                        <span class="ctx-pill">Condominio: {{ $co->nombre }}</span>
                        <input type="hidden" name="id_condominio" value="{{ $co->id_condominio }}">
                    @elseif($condos->count() > 1)
                        <label for="id_condominio" class="inline-label">Condominio</label>
                        <select id="id_condominio" name="id_condominio" class="control" style="min-width:200px" required>
                            @foreach ($condos as $co)
                                <option value="{{ $co->id_condominio }}" @selected((string)request('id_condominio') === (string)$co->id_condominio)>
                                    {{ $co->nombre }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                @endif

                {{-- Entidad --}}
                <label for="entidad" class="inline-label">Entidad</label>
                <select id="entidad" name="entidad" class="control">
                    <option value="">(todas)</option>
                    @forelse ($entidades as $e)
                        <option value="{{ $e }}" @selected(($entidad ?? '') == $e)>{{ $e }}</option>
                    @empty
                        <option value="" disabled>— Sin entidades —</option>
                    @endforelse
                </select>

                <button class="btn" @if($sinCondominiosAsignados && !$esSuper) disabled @endif>Filtrar</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="center">Fecha</th>
                        <th class="center">Entidad</th>
                        <th class="center">ID</th>
                        <th class="center">Acción</th>
                        <th class="center">Usuario</th>
                        <th class="center">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reg as $a)
                        @php
                            $rowId = 'audModal_' . $loop->index;

                            // Normaliza el detalle (json string | array | plain)
                            $raw = $a->detalle ?? '';
                            $parsed = null;
                            if (is_string($raw)) {
                                $tmp = json_decode($raw, true);
                                $parsed = is_array($tmp) ? $tmp : null;
                            } elseif (is_array($raw)) {
                                $parsed = $raw;
                            }

                            // ¿Diff old/new?
                            $isDiff = is_array($parsed) && (isset($parsed['old']) || isset($parsed['new']));
                            $old    = $isDiff ? (array)($parsed['old'] ?? []) : [];
                            $new    = $isDiff ? (array)($parsed['new'] ?? []) : [];
                            $keys   = $isDiff ? array_values(array_unique(array_merge(array_keys($old), array_keys($new)))) : [];
                            $changed= 0;
                            foreach ($keys as $k) {
                                if (($old[$k] ?? null) !== ($new[$k] ?? null)) $changed++;
                            }
                            $countKV = is_array($parsed) && !$isDiff ? count($parsed) : 0;

                            $fmt = function($v){
                                if (is_bool($v)) return $v ? 'true' : 'false';
                                if ($v === null) return 'null';
                                if (is_scalar($v)) return (string)$v;
                                $j = json_encode($v, JSON_UNESCAPED_UNICODE);
                                return $j ?? '';
                            };
                        @endphp
                        <tr>
                            <td class="center">{{ \Illuminate\Support\Str::limit((string) ($a->created_at ?? ''), 19, '') }}</td>
                            <td class="center">{{ $a->entidad ?? '' }}</td>
                            <td class="center">{{ $a->entidad_id ?? '' }}</td>
                            <td class="center">{{ $a->accion ?? '' }}</td>
                            <td class="center">{{ $a->usuario_email ?? '' }}</td>
                            <td class="center">
                                <button type="button" class="btn btn--sm" onclick="openModal('{{ $rowId }}')">Ver detalle</button>

                                {{-- Modal por fila --}}
                                <div id="{{ $rowId }}" class="modal-backdrop" aria-hidden="true" data-close-outside="true">
                                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $rowId }}_title">
                                        <header>
                                            <h4 id="{{ $rowId }}_title">
                                                Detalle — {{ $a->entidad ?? '' }} #{{ $a->entidad_id ?? '' }}
                                            </h4>
                                            <button class="close-btn" aria-label="Cerrar" onclick="closeModal('{{ $rowId }}')">✕</button>
                                        </header>
                                        <div class="body">
                                            <div class="meta" style="margin-bottom:6px;">
                                                {{ \Illuminate\Support\Str::limit((string) ($a->created_at ?? ''), 19, '') }}
                                                —
                                                {{ $a->usuario_email ?? '' }} —
                                                <span class="pill">{{ $a->accion ?? '' }}</span>
                                            </div>

                                            @if ($isDiff)
                                                <table class="mini">
                                                    <thead>
                                                        <tr>
                                                            <th>Campo</th>
                                                            <th>Antes</th>
                                                            <th class="arrow">→</th>
                                                            <th>Después</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($keys as $k)
                                                            @php
                                                                $before = $old[$k] ?? '';
                                                                $after  = $new[$k] ?? '';
                                                                $rowChanged = $before !== $after;
                                                            @endphp
                                                            <tr class="{{ $rowChanged ? 'row-changed' : 'row-same' }}">
                                                                <td class="k">{{ $k }}</td>
                                                                <td class="v">{{ $fmt($before) }}</td>
                                                                <td class="arrow">→</td>
                                                                <td class="v">{{ $fmt($after) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                <div class="meta" style="margin-top:8px;">Cambios: <strong>{{ $changed }}</strong></div>
                                            @elseif(is_array($parsed))
                                                <table class="mini">
                                                    <thead>
                                                        <tr>
                                                            <th>Clave</th>
                                                            <th>Valor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($parsed as $k => $v)
                                                            <tr>
                                                                <td class="k">{{ is_int($k) ? '#' . $k : $k }}</td>
                                                                <td class="v">{{ $fmt($v) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                <div class="meta" style="margin-top:8px;">Campos: <strong>{{ $countKV }}</strong></div>
                                            @else
                                                <div class="plain">{{ is_string($raw) ? $raw : $fmt($raw) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">
                                @if ($sinCondominiosAsignados && $esAdminLike && !$esSuper)
                                    No puedes ver registros porque no tienes condominios asignados.
                                @else
                                    Sin registros para los filtros seleccionados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }
        // Cerrar con clic fuera SI el modal tiene data-close-outside="true"
        document.addEventListener('click', (e) => {
            const backdrop = e.target.closest('.modal-backdrop');
            if (!backdrop) return;
            const clickedBackdropItself = (e.target === backdrop);
            const allowed = backdrop.dataset.closeOutside === 'true';
            if (backdrop.classList.contains('is-open') && allowed && clickedBackdropItself) {
                closeModal(backdrop.id);
            }
        });
        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.is-open').forEach(el => {
                    el.classList.remove('is-open');
                    el.setAttribute('aria-hidden', 'true');
                });
            }
        });
    </script>
@endsection
