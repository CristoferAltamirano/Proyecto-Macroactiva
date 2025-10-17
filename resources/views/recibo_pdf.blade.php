<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Recibo #{{ $p->id_pago }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #0f172a;
            font-size: 12px;
        }

        .wrap {
            max-width: 720px;
            margin: 0 auto;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 6px 0;
        }

        .muted {
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
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
    <div class="wrap">
        <h1>Recibo de Pago</h1>
        <div class="muted">Emitido automáticamente por MacroActiva</div>

        <div class="box">
            <table>
                <tr>
                    <th>N° Recibo</th>
                    <td>#{{ $p->id_pago }}</td>
                </tr>
                <tr>
                    <th>Condominio</th>
                    <td>{{ $condo->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Unidad</th>
                    <td>{{ $p->unidad_codigo ?? $p->id_unidad }}</td>
                </tr>
                <tr>
                    <th>Fecha de pago</th>
                    <td>{{ $p->fecha_pago }}</td>
                </tr>
                <tr>
                    <th>Periodo</th>
                    <td>{{ $p->periodo ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Método</th>
                    <td>{{ $metodo->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Referencia</th>
                    <td>{{ $p->ref_externa ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="right">Monto</th>
                    <td class="right"><strong>${{ number_format($p->monto, 0, ',', '.') }}</strong></td>
                </tr>
            </table>
        </div>

        <p class="muted" style="margin-top:10px">Gracias por su pago.</p>
    </div>
</body>

</html>
