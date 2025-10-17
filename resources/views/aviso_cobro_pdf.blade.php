<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Aviso de cobro {{ $c->periodo }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #0f172a;
            font-size: 12px;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 8px 0;
        }

        .muted {
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .right {
            text-align: right
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <h1>{{ $c->condominio }} — Unidad {{ $c->unidad }}</h1>
    <p class="muted">Aviso de cobro período {{ $c->periodo }}. Emitido:
        {{ \Illuminate\Support\Carbon::parse($c->emitido_at)->format('Y-m-d') }}</p>

    <div class="box">
        <table>
            <tr>
                <th>Total cargos</th>
                <td>${{ number_format($c->total_cargos, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Interés</th>
                <td>${{ number_format($c->total_interes, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Descuentos</th>
                <td>${{ number_format($c->total_descuentos, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Pagado</th>
                <td>${{ number_format($c->total_pagado, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th class="right">Saldo</th>
                <td class="right"><strong>${{ number_format($c->saldo, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <strong>Detalle</strong>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Glosa</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($det as $d)
                    <tr>
                        <td>{{ $d->tipo }}</td>
                        <td>{{ $d->glosa }}</td>
                        <td>${{ number_format($d->monto, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="muted" style="margin-top:10px">Sello: {{ $sig }}</p>
</body>

</html>
