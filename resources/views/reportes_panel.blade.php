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

        .card form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ===== Formularios (pares label+input) ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 12px;
            justify-items: center;
            align-items: center;
            max-width: 900px;
            width: 100%;
        }

        .pair {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            min-width: 220px;
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
            width: 100%;
            margin-top: 8px;
        }

        /* ===== Mini KPIs (totales) ===== */
        .mini-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            width: 100%;
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
        th.num {
            text-align: right;
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
            text-align: center;
        }

        /* ===== Utilidades ===== */
        .mt-10 {
            margin-top: 10px;
        }

        .mt-12 {
            margin-top: 12px;
        }
    </style>

    <div class="cards-2">
        {{-- Antigüedad de saldos (detalle cobros) --}}
        <div class="card">
            <h3>Antigüedad de saldos</h3>

            <form method="POST" action="{{ route('admin.reportes.antiguedad') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="ant_condo">Condominio</label>
                        <select id="ant_condo" name="id_condominio" class="control" required>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pair">
                        <label for="ant_corte">Fecha de corte</label>
                        <input id="ant_corte" type="date" name="fecha_corte" value="{{ now()->toDateString() }}"
                            class="control" required>
                    </div>
                </div>
                <div class="actions"><button class="btn">Calcular</button></div>
            </form>

            @if ($ant)
                <form method="POST" action="{{ route('admin.reportes.antiguedad.csv') }}" class="mt-10">
                    @csrf
                    <input type="hidden" name="id_condominio" value="{{ $ant['filtro']['id_condominio'] }}">
                    <input type="hidden" name="fecha_corte" value="{{ $ant['filtro']['fecha_corte'] }}">
                    <div class="actions"><button class="btn">Descargar CSV</button></div>
                </form>

                <div class="mini-cards mt-10">
                    <div class="mini"><strong>0–30:</strong> ${{ number_format($ant['totales']['p0_30'], 0, ',', '.') }}
                    </div>
                    <div class="mini"><strong>31–60:</strong> ${{ number_format($ant['totales']['p31_60'], 0, ',', '.') }}
                    </div>
                    <div class="mini"><strong>61–90:</strong> ${{ number_format($ant['totales']['p61_90'], 0, ',', '.') }}
                    </div>
                    <div class="mini"><strong>&gt;90:</strong> ${{ number_format($ant['totales']['p90'], 0, ',', '.') }}
                    </div>
                    <div class="mini"><strong>Total:</strong> ${{ number_format($ant['totales']['total'], 0, ',', '.') }}
                    </div>
                </div>

                <table class="mt-12">
                    <thead>
                        <tr>
                            <th>ID Cobro</th>
                            <th>Unidad</th>
                            <th>Periodo</th>
                            <th>Días</th>
                            <th class="num">Saldo</th>
                            <th>Tramo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ant['detalle'] as $d)
                            <tr>
                                <td>{{ $d['id_cobro'] }}</td>
                                <td>{{ $d['unidad'] }}</td>
                                <td>{{ $d['periodo'] }}</td>
                                <td>{{ $d['dias'] }}</td>
                                <td class="num">${{ number_format($d['saldo'], 0, ',', '.') }}</td>
                                <td>{{ $d['bucket'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Recaudación --}}
        <div class="card">
            <h3>Recaudación por periodo y método</h3>

            <form method="POST" action="{{ route('admin.reportes.recaudacion') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="rec_condo">Condominio</label>
                        <select id="rec_condo" name="id_condominio" class="control" required>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pair">
                        <label for="rec_desde">Desde (AAAAMM)</label>
                        <input id="rec_desde" type="text" name="desde" placeholder="Desde AAAAMM" pattern="[0-9]{6}"
                            class="control" required>
                    </div>
                    <div class="pair">
                        <label for="rec_hasta">Hasta (AAAAMM)</label>
                        <input id="rec_hasta" type="text" name="hasta" placeholder="Hasta AAAAMM" pattern="[0-9]{6}"
                            class="control" required>
                    </div>
                </div>
                <div class="actions"><button class="btn">Calcular</button></div>
            </form>

            @if ($rec)
                <form method="POST" action="{{ route('admin.reportes.recaudacion.csv') }}" class="mt-10">
                    @csrf
                    <input type="hidden" name="id_condominio" value="{{ $rec['filtro']['id_condominio'] }}">
                    <input type="hidden" name="desde" value="{{ $rec['filtro']['desde'] }}">
                    <input type="hidden" name="hasta" value="{{ $rec['filtro']['hasta'] }}">
                    <div class="actions"><button class="btn">Descargar CSV</button></div>
                </form>

                @php
                    $metodos = array_keys($rec['metodos_tot']);
                    sort($metodos);
                @endphp
                <table class="mt-12">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            @foreach ($metodos as $m)
                                <th>{{ $m }}</th>
                            @endforeach
                            <th>Total periodo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rec['tabla'] as $per => $arr)
                            @php $s=0; @endphp
                            <tr>
                                <td>{{ $per }}</td>
                                @foreach ($metodos as $m)
                                    @php
                                        $v = $arr[$m] ?? 0;
                                        $s += $v;
                                    @endphp
                                    <td class="num">${{ number_format($v, 0, ',', '.') }}</td>
                                @endforeach
                                <td class="num"><strong>${{ number_format($s, 0, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>TOTAL</strong></td>
                            @foreach ($metodos as $m)
                                <td class="num">
                                    <strong>${{ number_format($rec['metodos_tot'][$m] ?? 0, 0, ',', '.') }}</strong></td>
                            @endforeach
                            <td class="num"><strong>${{ number_format($rec['total'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Antigüedad por unidad (pivot) --}}
        <div class="card">
            <h3>Antigüedad por unidad (pivot)</h3>

            <form method="POST" action="{{ route('admin.reportes.antiguedad.pivot') }}">
                @csrf
                <div class="form-grid">
                    <div class="pair">
                        <label for="ap_condo">Condominio</label>
                        <select id="ap_condo" name="id_condominio" class="control" required>
                            @foreach ($condos as $c)
                                <option value="{{ $c->id_condominio }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pair">
                        <label for="ap_corte">Fecha de corte</label>
                        <input id="ap_corte" type="date" name="fecha_corte" value="{{ now()->toDateString() }}"
                            class="control" required>
                    </div>
                </div>
                <div class="actions"><button class="btn">Calcular</button></div>
            </form>

            @if ($antPivot)
                <form method="POST" action="{{ route('admin.reportes.antiguedad.pivot.csv') }}" class="mt-10">
                    @csrf
                    <input type="hidden" name="id_condominio" value="{{ $antPivot['filtro']['id_condominio'] }}">
                    <input type="hidden" name="fecha_corte" value="{{ $antPivot['filtro']['fecha_corte'] }}">
                    <div class="actions"><button class="btn">Descargar CSV</button></div>
                </form>

                <table class="mt-12">
                    <thead>
                        <tr>
                            <th>Unidad</th>
                            <th class="num">0–30</th>
                            <th class="num">31–60</th>
                            <th class="num">61–90</th>
                            <th class="num">&gt;90</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($antPivot['tabla'] as $u => $row)
                            <tr>
                                <td>{{ $u }}</td>
                                <td class="num">${{ number_format($row['p0_30'] ?? 0, 0, ',', '.') }}</td>
                                <td class="num">${{ number_format($row['p31_60'] ?? 0, 0, ',', '.') }}</td>
                                <td class="num">${{ number_format($row['p61_90'] ?? 0, 0, ',', '.') }}</td>
                                <td class="num">${{ number_format($row['p90'] ?? 0, 0, ',', '.') }}</td>
                                <td class="num"><strong>${{ number_format($row['total'] ?? 0, 0, ',', '.') }}</strong>
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>TOTAL</strong></td>
                            <td class="num">
                                <strong>${{ number_format($antPivot['totales']['p0_30'], 0, ',', '.') }}</strong></td>
                            <td class="num">
                                <strong>${{ number_format($antPivot['totales']['p31_60'], 0, ',', '.') }}</strong></td>
                            <td class="num">
                                <strong>${{ number_format($antPivot['totales']['p61_90'], 0, ',', '.') }}</strong></td>
                            <td class="num">
                                <strong>${{ number_format($antPivot['totales']['p90'], 0, ',', '.') }}</strong></td>
                            <td class="num">
                                <strong>${{ number_format($antPivot['totales']['total'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
