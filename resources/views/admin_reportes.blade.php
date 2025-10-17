@extends('layout')
@section('title', 'Reportes')
@section('content')
    @include('partials.flash')

    <style>
        /* ===== Grid de cards (2 col responsive) ===== */
        .cards-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media(max-width:900px) {
            .cards-2 {
                grid-template-columns: 1fr;
            }
        }

        /* ===== Cards centrados ===== */
        .card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .card h3 {
            margin-bottom: 8px;
        }

        .card h4 {
            margin-bottom: 6px;
        }

        .card form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ===== Formularios ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 12px;
            justify-items: center;
            align-items: center;
            width: 100%;
            max-width: 900px;
        }

        .pair {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            min-width: 200px;
        }

        .pair label {
            font-weight: 600;
        }

        .control {
            width: 100%;
            max-width: 280px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin: 0 auto;
        }

        .actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        /* ===== Mini KPIs (totales) ===== */
        .mini-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            width: 100%;
            margin-top: 10px;
        }

        .mini {
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            font-size: 15px;
        }

        /* ===== Tablas ===== */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        thead th {
            font-weight: 700;
        }

        td.num,
        th.num,
        .right {
            text-align: right;
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
            text-align: center;
        }

        /* Utilidades */
        .mt-12 {
            margin-top: 12px;
        }
    </style>

    <div class="cards-2">
        <!-- Antigüedad -->
        <div class="card">
            <h3>Antigüedad de saldos</h3>
            <form method="POST" action="{{ route('admin.reportes.antiguedad') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="ant_corte">Corte (AAAAMM)</label>
                        <input id="ant_corte" name="corte" value="{{ old('corte', $defaults['corte']) }}"
                            placeholder="Corte (AAAAMM)" pattern="[0-9]{6}" inputmode="numeric" class="control" required>
                    </div>
                    <div class="pair">
                        <label for="ant_condo">Condominio</label>
                        <select id="ant_condo" name="id_condominio" class="control">
                            <option value="">Todos los condominios</option>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}" @selected(old('id_condominio', $resultado['id_condominio'] ?? null) == $c->id_condominio)>
                                    {{ $c->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn">Ver</button>
                    <button class="btn" formaction="{{ route('admin.reportes.antiguedad.csv') }}">CSV</button>
                </div>
            </form>
        </div>

        <!-- Recaudación -->
        <div class="card">
            <h3>Recaudación</h3>
            <form method="POST" action="{{ route('admin.reportes.recaudacion') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="rec_desde">Desde (AAAAMM)</label>
                        <input id="rec_desde" name="desde" value="{{ old('desde', $defaults['desde']) }}"
                            placeholder="Desde AAAAMM" pattern="[0-9]{6}" inputmode="numeric" class="control" required>
                    </div>
                    <div class="pair">
                        <label for="rec_hasta">Hasta (AAAAMM)</label>
                        <input id="rec_hasta" name="hasta" value="{{ old('hasta', $defaults['hasta']) }}"
                            placeholder="Hasta AAAAMM" pattern="[0-9]{6}" inputmode="numeric" class="control" required>
                    </div>
                    <div class="pair">
                        <label for="rec_condo">Condominio</label>
                        <select id="rec_condo" name="id_condominio" class="control">
                            <option value="">Todos los condominios</option>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}" @selected(old('id_condominio') == $c->id_condominio)>
                                    {{ $c->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn">Ver</button>
                    <button class="btn" formaction="{{ route('admin.reportes.recaudacion.csv') }}">CSV</button>
                </div>
            </form>
        </div>

        <!-- Antigüedad Pivot -->
        <div class="card">
            <h3>Antigüedad (Pivot por condominio)</h3>
            <form method="POST" action="{{ route('admin.reportes.antiguedad.pivot') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="ap_corte">Corte (AAAAMM)</label>
                        <input id="ap_corte" name="corte" value="{{ old('corte', $defaults['corte']) }}"
                            placeholder="Corte (AAAAMM)" pattern="[0-9]{6}" inputmode="numeric" class="control" required>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn">Ver</button>
                    <button class="btn" formaction="{{ route('admin.reportes.antiguedad.pivot.csv') }}">CSV</button>
                </div>
            </form>
        </div>
    </div>

    @if ($resultado && $resultado['tipo'] === 'antiguedad')
        <div class="card mt-12">
            <h3>Antigüedad — Corte {{ $resultado['corte'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th>Unidad</th>
                        <th>Periodo</th>
                        <th>Meses</th>
                        <th>Bucket</th>
                        <th class="num">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultado['rows'] as $r)
                        <tr>
                            <td>{{ $r['condominio'] }}</td>
                            <td>{{ $r['unidad'] }}</td>
                            <td>{{ $r['periodo'] }}</td>
                            <td>{{ $r['meses'] }}</td>
                            <td>{{ $r['bucket'] }}</td>
                            <td class="num">${{ number_format($r['saldo'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">Sin saldos vencidos.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="right">0-30</th>
                        <th class="num">${{ number_format($resultado['totales']['0-30'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">31-60</th>
                        <th class="num">${{ number_format($resultado['totales']['31-60'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">61-90</th>
                        <th class="num">${{ number_format($resultado['totales']['61-90'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">91-180</th>
                        <th class="num">${{ number_format($resultado['totales']['91-180'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">181-360</th>
                        <th class="num">${{ number_format($resultado['totales']['181-360'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">361+</th>
                        <th class="num">${{ number_format($resultado['totales']['361+'], 0, ',', '.') }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="right">TOTAL</th>
                        <th class="num">${{ number_format($resultado['totales']['TOTAL'], 0, ',', '.') }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @if ($resultado && $resultado['tipo'] === 'recaudacion')
        <div class="card mt-12">
            <h3>Recaudación — {{ $resultado['desde'] }} a {{ $resultado['hasta'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th>Periodo</th>
                        <th>Método</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultado['rows'] as $r)
                        <tr>
                            <td>{{ $r->condominio }}</td>
                            <td>{{ $r->periodo_pago }}</td>
                            <td>{{ $r->metodo ?? '—' }}</td>
                            <td class="num">${{ number_format($r->total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">Sin pagos en rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card" style="margin-top:12px">
                <h4>Totales por periodo</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Condominio</th>
                            <th>Periodo</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultado['porPeriodo'] as $p)
                            <tr>
                                <td>{{ $p['condominio'] }}</td>
                                <td>{{ $p['periodo'] }}</td>
                                <td class="num">${{ number_format($p['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($resultado && $resultado['tipo'] === 'antiguedad_pivot')
        <div class="card mt-12">
            <h3>Antigüedad (Pivot) — Corte {{ $resultado['corte'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Condominio</th>
                        <th class="num">0-30</th>
                        <th class="num">31-60</th>
                        <th class="num">61-90</th>
                        <th class="num">91-180</th>
                        <th class="num">181-360</th>
                        <th class="num">361+</th>
                        <th class="num">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultado['pivot'] as $condo => $vals)
                        <tr>
                            <td>{{ $condo }}</td>
                            <td class="num">${{ number_format($vals['0-30'], 0, ',', '.') }}</td>
                            <td class="num">${{ number_format($vals['31-60'], 0, ',', '.') }}</td>
                            <td class="num">${{ number_format($vals['61-90'], 0, ',', '.') }}</td>
                            <td class="num">${{ number_format($vals['91-180'], 0, ',', '.') }}</td>
                            <td class="num">${{ number_format($vals['181-360'], 0, ',', '.') }}</td>
                            <td class="num">${{ number_format($vals['361+'], 0, ',', '.') }}</td>
                            <td class="num"><strong>${{ number_format($vals['TOTAL'], 0, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Sin saldos.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th>TOTALES</th>
                        <th class="num">${{ number_format($resultado['total']['0-30'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['31-60'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['61-90'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['91-180'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['181-360'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['361+'], 0, ',', '.') }}</th>
                        <th class="num">${{ number_format($resultado['total']['TOTAL'], 0, ',', '.') }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
@endsection
