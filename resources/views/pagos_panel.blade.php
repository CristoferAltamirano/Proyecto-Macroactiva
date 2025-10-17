@extends('layout')
@section('title', 'Pagos')

@section('content')
    @include('partials.flash')

    @php
        use Illuminate\Support\Str;
        use Illuminate\Support\Facades\DB;

        // ====== Contexto/rol
        $yo    = auth()->user();
        $rol   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $ctxId = (int) (session('ctx_condo_id') ?? 0);

        // Lo que venga del controller (fallback)
        $pagos = $pagos ?? collect();
        $tx    = $tx ?? collect();

        /* =====================================================
           Anti "info cruzada":
           Si es ADMIN y tenemos ctx de condominio,
           volvemos a consultar SOLO datos de ese condominio.
           ===================================================== */
        if ($rol === 'admin' && $ctxId > 0) {
            // Pagos del condominio activo (joins: unidad -> grupo -> condominio)
            $pagos = DB::table('pago as p')
                ->join('unidad as u', 'u.id_unidad', '=', 'p.id_unidad')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                ->where('g.id_condominio', $ctxId)
                ->orderByDesc('p.id_pago')
                ->limit(120)
                ->get([
                    'p.*',
                    DB::raw('u.codigo as unidad'),
                ]);

            // Transacciones de Webpay SOLO de pagos del condominio activo
            $tx = DB::table('pasarela_tx as t')
                ->leftJoin('pago as p', 'p.id_pago', '=', 't.id_pago')
                ->leftJoin('unidad as u', 'u.id_unidad', '=', 'p.id_unidad')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                ->where('g.id_condominio', $ctxId)
                ->orderByDesc('t.id_pasarela_tx')
                ->limit(120)
                ->get(['t.*']);
        }

        // 1 query para comprobantes por id_pago (de lo filtrado arriba)
        $compByPago = $pagos->isEmpty()
            ? collect()
            : DB::table('comprobante_pago')
                ->whereIn('id_pago', $pagos->pluck('id_pago'))
                ->get()
                ->keyBy('id_pago');

        /* =====================================================
           Selector de unidades para el formulario:
           - Admin: solo del condominio activo
           - Otros roles: todas (límite 800)
           ===================================================== */
        $unidades = DB::table('unidad as u')
            ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
            ->when($rol === 'admin' && $ctxId > 0, function ($q) use ($ctxId) {
                $q->where('g.id_condominio', $ctxId);
            })
            ->orderBy('u.id_unidad')
            ->limit(800)
            ->get(['u.id_unidad', 'u.codigo']);
    @endphp

    <style>
        /* ========= Scoped a esta página ========= */

        /* Helpers */
        .center { text-align: center; }
        .right  { text-align: right;  }
        .muted  { color: #64748b;     }

        /* Titulitos */
        .card h3{ text-align:center; margin:0 0 2px; font-weight:800; color:#111827; }
        .card h3+.sub{ margin-top:6px; text-align:center; color:#475569; }

        /* ====== FORM ====== */
        .form-grid-2{
            display:grid; gap:16px 32px; margin-top:18px;
            grid-template-columns: repeat(2, minmax(260px, 1fr));
            justify-items:center;
        }
        .form-field{ display:flex; flex-direction:column; align-items:stretch; gap:6px; width:100%; max-width:380px; }
        .form-field label{ font-weight:600; color:#111827; text-align:center; }
        .form-field label:not(.no-colon)::after{ content: ':'; }
        .control{ width:100%; padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; color:#0f172a; outline:0; transition: border-color .15s, box-shadow .15s; text-align:center; box-shadow: var(--shadow-xs); }
        select.control{ text-align-last:center; }
        .control:focus{ border-color:#c7d2fe; box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        .col-span-2{ grid-column:1 / -1; }
        .form-actions{ display:flex; justify-content:center; padding-top:2px; }
        .form-actions .btn{ min-width:120px; }

        /* ====== TABLAS ====== */
        .table-wrap{ border:1px solid #e5e7eb; border-radius:12px; overflow:auto; background:#fff; }
        table{ width:100%; border-collapse:collapse; }
        thead th{ position:sticky; top:0; z-index:1; background:#f8fafc; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:.02em; font-size:.9rem; border-bottom:1px solid #e5e7eb; }
        th, td{ padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#111827; }
        tbody tr:hover{ background:#f9fafb; }

        .badge{ display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border-radius:999px; font-size:.78rem; font-weight:600; background:#eef2ff; color:#3730a3; }
        .badge.gray{ background:#f1f5f9; color:#334155; }
        .badge.green{ background:#ecfdf5; color:#065f46; }
        .badge.amber{ background:#fffbeb; color:#92400e; }
        .badge.red{ background:#fef2f2; color:#991b1b; }

        .btn-table{ padding:8px 12px; border-radius:10px; }
        .empty{ text-align:center; color:#64748b; padding:18px; }

        @media (max-width:720px){
            .form-grid-2{ grid-template-columns:1fr; gap:14px; }
            .col-span-2{ grid-column:auto; }
            .hide-sm{ display:none; }
            th, td{ padding:10px 12px; }
        }
    </style>

    {{-- ================== CARD: Registrar pago manual ================== --}}
    <div class="card">
        <h3>Registrar pago manual</h3>
        <p class="sub">Los asientos contables los generan los triggers de BD.</p>

        <form method="POST" action="{{ route('pagos.store') }}" class="form-grid-2">
            @csrf

            <div class="form-field">
                <label for="id_unidad">Unidad</label>
                <select id="id_unidad" name="id_unidad" required class="control">
                    <option value="" disabled selected>Selecciona una unidad…</option>
                    @foreach ($unidades as $u)
                        <option value="{{ $u->id_unidad }}" @selected(old('id_unidad') == $u->id_unidad)>
                            #{{ $u->id_unidad }} {{ $u->codigo ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="periodo">Periodo (AAAAMM)</label>
                <input id="periodo" type="text" name="periodo" pattern="[0-9]{6}" title="Formato: 6 dígitos (AAAAMM)"
                       placeholder="Opcional" class="control" inputmode="numeric" value="{{ old('periodo') }}">
            </div>

            <div class="form-field">
                <label for="monto">Monto</label>
                <input id="monto" type="number" step="0.01" min="0" name="monto" required class="control"
                       placeholder="Ej: 55000" inputmode="decimal" value="{{ old('monto') }}">
            </div>

            <div class="form-field">
                <label for="id_metodo_pago">Método de pago</label>
                <select id="id_metodo_pago" name="id_metodo_pago" class="control">
                    @foreach ($metodos ?? collect() as $m)
                        <option value="{{ $m->id_metodo_pago }}" @selected(old('id_metodo_pago') == $m->id_metodo_pago)>
                            {{ $m->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field col-span-2">
                <label for="ref_externa">Referencia externa</label>
                <input id="ref_externa" type="text" name="ref_externa"
                       placeholder="Comprobante banco, glosa, etc. (opcional)" class="control"
                       value="{{ old('ref_externa') }}">
            </div>

            <div class="col-span-2 form-actions">
                <button class="btn btn--sm">Guardar</button>
            </div>
        </form>
    </div>

    {{-- ================== CARD: Últimos pagos ================== --}}
    <div class="card">
        <h3>Últimos pagos</h3>

        <div class="table-wrap" style="margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th class="center">ID</th>
                        <th>Unidad</th>
                        <th class="center">Periodo</th>
                        <th class="right">Monto</th>
                        <th class="center">Fecha</th>
                        <th class="hide-sm">Ref</th>
                        <th class="center">Acción</th>
                        <th class="center">Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pagos as $p)
                        @php $comp = $compByPago->get($p->id_pago); @endphp
                        <tr>
                            <td class="center">{{ $p->id_pago }}</td>
                            <td><span class="badge gray">U{{ $p->unidad ?? $p->id_unidad }}</span></td>
                            <td class="center">{{ $p->periodo ?: '—' }}</td>
                            <td class="right">${{ number_format($p->monto, 0, ',', '.') }}</td>
                            <td class="center">{{ Str::limit((string) $p->fecha_pago, 19, '') }}</td>
                            <td class="muted hide-sm">{{ $p->ref_externa ?: '—' }}</td>
                            <td class="center">
                                <a class="btn btn--sm btn-table"
                                   href="{{ route('pagos.aprobar.demo', $p->id_pago) }}">Aprobar demo</a>
                            </td>
                            <td class="center">
                                @if ($comp)
                                    <a class="btn btn--ghost btn--sm btn-table"
                                       href="{{ route('comprobante.pdf', $comp->id_compr_pago) }}" target="_blank">
                                        {{ $comp->folio }}
                                    </a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">Sin pagos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================== CARD: Transacciones Webpay ================== --}}
    <div class="card">
        <h3>Transacciones Webpay</h3>

        <div class="table-wrap" style="margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th class="center">ID TX</th>
                        <th class="center">Pago</th>
                        <th class="right">Monto</th>
                        <th class="center">Estado</th>
                        <th class="center">Creado</th>
                        <th class="center">Actualizado</th>
                        <th class="hide-sm">Token</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tx as $t)
                        @php
                            $badge = 'gray';
                            $state = strtoupper($t->estado ?? '—');
                            if (in_array($state, ['AUTORIZADA', 'APROBADA', 'OK'])) {
                                $badge = 'green';
                            } elseif (in_array($state, ['PENDIENTE', 'INICIADA'])) {
                                $badge = 'amber';
                            } elseif (in_array($state, ['RECHAZADA', 'ERROR'])) {
                                $badge = 'red';
                            }
                        @endphp
                        <tr>
                            <td class="center">{{ $t->id_pasarela_tx }}</td>
                            <td class="center">#{{ $t->id_pago ?: '—' }}</td>
                            <td class="right">${{ number_format($t->monto ?? 0, 0, ',', '.') }}</td>
                            <td class="center"><span class="badge {{ $badge }}">{{ $state }}</span></td>
                            <td class="center">{{ Str::limit((string) $t->created_at, 19, '') }}</td>
                            <td class="center">{{ Str::limit((string) $t->updated_at, 19, '') }}</td>
                            <td class="muted hide-sm">{{ $t->token ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">Sin transacciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
