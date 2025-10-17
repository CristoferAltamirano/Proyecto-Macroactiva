@extends('layout')
@section('title', 'Cierres')
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

        $ctx = (int) (session('ctx_condo_id') ?? 0);

        $prevMonth = \Carbon\Carbon::now()->subMonth()->format('Ym');
        $thisMonth = \Carbon\Carbon::now()->format('Ym');
        $thisYear = \Carbon\Carbon::now()->year;

        $cerrados = DB::table('periodo_cierre as pc')
            ->when($ctx > 0, fn($q) => $q->where('pc.id_condominio', $ctx))
            ->orderByDesc('pc.periodo')
            ->limit(120)
            ->get();

        $cerradosAnio = DB::table('cierre_anual as ca')
            ->when($ctx > 0, fn($q) => $q->where('ca.id_condominio', $ctx))
            ->orderByDesc('ca.anio')
            ->limit(30)
            ->get();

        $userEmails = [];
        if ($cerrados->isNotEmpty()) {
            $uids = $cerrados->pluck('cerrado_por')->filter()->unique()->all();
            if ($uids) {
                $userEmails = DB::table('usuario')->whereIn('id_usuario', $uids)->pluck('email', 'id_usuario')->all();
            }
        }
        if ($cerradosAnio->isNotEmpty()) {
            $uidsA = $cerradosAnio->pluck('cerrado_por')->filter()->unique()->all();
            if ($uidsA) {
                $userEmailsA = DB::table('usuario')->whereIn('id_usuario', $uidsA)->pluck('email', 'id_usuario')->all();
                $userEmails = array_replace($userEmails, $userEmailsA);
            }
        }
    @endphp

    <style>
        .top-grid,
        .bottom-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr;
        }

        @media (min-width: 980px) {

            .top-grid,
            .bottom-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .card h3,
        .card p {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        /* Base: fila con input + acciones (centrado por defecto) */
        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .field {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .field label {
            font-weight: 600;
            text-align: center;
        }

        .control {
            width: 140px;
            padding: 6px 8px;
            font-size: .92rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            text-align: center;
            box-shadow: var(--shadow-xs);
            outline: 0;
            transition: border-color .15s, box-shadow .15s;
        }

        .control:focus {
            border-color: #c7d2fe;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
        }

        .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        /* Tablas con contenedor y encabezado fijo */
        .table-wrap {
            width: 100%;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--shadow-xs);
        }

        .table-flat {
            width: 100%;
            border-collapse: collapse;
        }

        .table-flat thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
            font-size: .9rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }

        .table-flat th,
        .table-flat td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
            text-align: center;
            white-space: nowrap;
        }

        .table-flat tbody tr:hover {
            background: #f9fafb;
        }

        @media (max-width:640px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .actions {
                justify-content: center;
            }

            .field {
                flex-direction: column;
            }

            .control {
                width: 100%;
            }

            .table-flat th,
            .table-flat td {
                padding: 10px 12px;
            }
        }

        /* =========================
         Overrides para alinear a la izquierda y
         dejar botones AL LADO del input
         ========================= */
        /* Cierre Mensual */
        #card-mensual h3,
        #card-mensual p {
            text-align: left;
        }

        #card-mensual .form-row {
            display: flex;
            /* << cambiar a flex */
            align-items: flex-end;
            /* alinear bordes inferiores */
            gap: 12px;
            flex-wrap: wrap;
            /* evita overflow en pantallas muy estrechas */
        }

        #card-mensual .field {
            justify-content: flex-start;
            /* label+input a la izquierda */
        }

        #card-mensual .field label {
            text-align: left;
        }

        #card-mensual .control {
            text-align: left;
            /* texto del input a la izquierda */
            min-width: 140px;
        }

        #card-mensual .actions {
            justify-content: flex-start;
            /* botones a la izquierda */
            flex: 0 0 auto;
            /* no ocupar más de lo necesario */
            flex-wrap: nowrap;
            /* mantenerlos en línea mientras haya espacio */
        }

        /* Cierre Anual */
        #card-anual .form-row {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        #card-anual .field {
            justify-content: flex-start;
        }

        #card-anual .actions {
            justify-content: flex-start;
            flex: 0 0 auto;
            flex-wrap: nowrap;
        }

        #card-anual .control {
            text-align: left;
            min-width: 120px;
        }
    </style>

    <div class="top-grid">
        <!-- ====== Cierre mensual (alineado a la izquierda y botones al lado) ====== -->
        <div class="card" id="card-mensual">
            <h3>Cierre Mensual</h3>
            <p class="muted">Indica el período en formato <strong>AAAAMM</strong>. Ej: 202501</p>

            {{-- Cerrar período --}}
            <form method="POST" action="{{ route('cierres.cerrar') }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label for="periodo-cerrar">Período</label>
                        <input id="periodo-cerrar" class="control" type="text" name="periodo" inputmode="numeric"
                            pattern="^[0-9]{6}$" title="Formato: 6 dígitos (AAAAMM)"
                            value="{{ old('periodo', $prevMonth) }}" required>
                    </div>
                    <div class="actions">
                        <button class="btn">Cerrar</button>
                        <button type="button" class="btn" onclick="setPeriodo('{{ $prevMonth }}')">Mes
                            anterior</button>
                        <button type="button" class="btn" onclick="setPeriodo('{{ $thisMonth }}')">Mes
                            actual</button>
                    </div>
                </div>
            </form>

            {{-- Reabrir período --}}
            <form method="POST" action="{{ route('cierres.reabrir') }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label for="periodo-reabrir">Período</label>
                        <input id="periodo-reabrir" class="control" type="text" name="periodo" inputmode="numeric"
                            pattern="^[0-9]{6}$" title="Formato: 6 dígitos (AAAAMM)"
                            value="{{ old('periodo', $prevMonth) }}" required>
                    </div>
                    <div class="actions">
                        <button class="btn">Reabrir</button>
                    </div>
                </div>
            </form>

            {{-- Status --}}
            <form method="GET" action="{{ route('cierres.status') }}">
                <div class="form-row">
                    <div class="field">
                        <label for="periodo-status">Período</label>
                        <input id="periodo-status" class="control" type="text" name="periodo" inputmode="numeric"
                            pattern="^[0-9]{6}$" title="Formato: 6 dígitos (AAAAMM)"
                            value="{{ old('periodo', $prevMonth) }}" required>
                    </div>
                    <div class="actions"><button class="btn">Status</button></div>
                </div>
            </form>

            {{-- PDF --}}
            <form method="GET" action="{{ route('cierres.pdf') }}">
                <div class="form-row">
                    <div class="field">
                        <label for="periodo-pdf">Período</label>
                        <input id="periodo-pdf" class="control" type="text" name="periodo" inputmode="numeric"
                            pattern="^[0-9]{6}$" title="Formato: 6 dígitos (AAAAMM)"
                            value="{{ old('periodo', $prevMonth) }}" required>
                    </div>
                    <div class="actions"><button class="btn">PDF</button></div>
                </div>
            </form>

            {{-- Diff --}}
            <form method="GET" action="{{ route('cierres.diff') }}">
                <div class="form-row">
                    <div class="field">
                        <label for="periodo-diff">Período</label>
                        <input id="periodo-diff" class="control" type="text" name="periodo" inputmode="numeric"
                            pattern="^[0-9]{6}$" title="Formato: 6 dígitos (AAAAMM)"
                            value="{{ old('periodo', $prevMonth) }}" required>
                    </div>
                    <div class="actions"><button class="btn">Diff</button></div>
                </div>
            </form>
        </div>

        <!-- ====== Cierre anual (botones al lado del input) ====== -->
        <div class="card" id="card-anual">
            <h3>Cierre Contable Anual</h3>
            <p class="muted">Cierra cuentas 4xxx y 5xxx contra 3201 Resultados acumulados al 31/12 del año indicado.</p>

            <form method="POST" action="{{ route('cierres.cerrar.anual') }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label for="anio-cerrar">Año</label>
                        <input id="anio-cerrar" class="control" type="number" name="anio" min="2000"
                            max="2100" value="{{ old('anio', $thisYear) }}" required>
                    </div>
                    <div class="actions"><button class="btn">Cerrar año</button></div>
                </div>
            </form>

            <form method="POST" action="{{ route('cierres.reabrir.anual') }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label for="anio-reabrir">Año</label>
                        <input id="anio-reabrir" class="control" type="number" name="anio" min="2000"
                            max="2100" value="{{ old('anio', $thisYear) }}" required>
                    </div>
                    <div class="actions"><button class="btn">Reabrir año</button></div>
                </div>
            </form>
        </div>
    </div>

    <!-- ====== Listados ====== -->
    <div class="bottom-grid" style="margin-top:16px">
        <div class="card">
            <h3>Periodos cerrados</h3>
            <div class="table-wrap" style="margin-top:10px">
                <table class="table table-flat">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Cerrado por</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cerrados as $c)
                            <tr>
                                <td>{{ $c->periodo }}</td>
                                <td>{{ $c->cerrado_por ? $userEmails[$c->cerrado_por] ?? 'Usuario #' . $c->cerrado_por : '—' }}
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $c->cerrado_at, 19, '') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">Aún no hay periodos cerrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>Años cerrados</h3>
            <div class="table-wrap" style="margin-top:10px">
                <table class="table table-flat">
                    <thead>
                        <tr>
                            <th>Año</th>
                            <th>Cerrado por</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cerradosAnio as $ca)
                            <tr>
                                <td>{{ $ca->anio }}</td>
                                <td>{{ $ca->cerrado_por ? $userEmails[$ca->cerrado_por] ?? 'Usuario #' . $ca->cerrado_por : '—' }}
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $ca->cerrado_at, 19, '') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">Aún no hay años cerrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function setPeriodo(val) {
            document.querySelectorAll('input[name="periodo"]').forEach(i => i.value = val);
        }
    </script>
@endsection
