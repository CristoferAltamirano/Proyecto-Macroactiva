<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre {{ $periodo }} – {{ $condo->nombre }}</title>
    <style>
        /* ===== Solo estética / impresión ===== */
        @page { margin: 18mm 14mm; }
        *{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#0f172a; font-size:12px; line-height:1.45; }
        h1{ font-size:18px; margin:0 0 6px 0; color:#111827; }
        h2{ font-size:14px; margin:14px 0 6px 0; color:#111827; }
        .muted{ color:#64748b }
        .box{ border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:10px; background:#fff; }
        table{ width:100%; border-collapse:collapse; background:#fff; }
        th,td{ padding:6px 8px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .right{ text-align:right }
        thead th{ background:#f8fafc; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:.02em; }
        tbody tr:nth-child(even){ background:#fcfdff; }
        thead{ display:table-header-group; }
        tfoot{ display:table-row-group; }
        tr{ page-break-inside:avoid; }
        .grid{ display:table; width:100%; }
        .col{ display:table-cell; width:50%; vertical-align:top; padding-right:8px; }
        .col:last-child{ padding-right:0; padding-left:8px; }
        .box table th{ width:28%; color:#475569; font-weight:700; }
    </style>
</head>
<body>
@php
    // ===== Anti "info cruzada" en PDF de cierre =====
    $yo      = auth()->user();
    $rol     = $yo->rol ?? ($yo->tipo_usuario ?? null);
    $ctxId   = (int) (session('ctx_condo_id') ?? 0);

    // Detección tolerante del ID de condominio en el objeto $condo
    $condoId = (int) ($condo->id_condominio ?? ($condo->condominio_id ?? 0));

    // Permitido si: super_admin, o no hay ctx (compat), o el condo del reporte coincide con el ctx
    $permitido = ($rol === 'super_admin') || ($ctxId === 0) || ($condoId === $ctxId);
@endphp

@if(!$permitido)
    <h1>Cierre mensual {{ $periodo }}</h1>
    <p class="muted">No estás autorizado para ver este cierre. Cambia al condominio correspondiente o solicita acceso.</p>
@else
    <h1>Cierre mensual {{ $periodo }}</h1>
    <div class="muted">Condominio: <strong>{{ $condo->nombre }}</strong> — Generado: {{ $generado_at }}</div>

    <div class="box">
        <table>
            <tr>
                <th>RUT</th>
                <td>{{ $condo->rut_base ? $condo->rut_base . '-' . $condo->rut_dv : '—' }}</td>
            </tr>
            <tr>
                <th>Dirección</th>
                <td>{{ $condo->direccion ?? '—' }}, {{ $condo->comuna ?? '' }}</td>
            </tr>
            <tr>
                <th>Cuenta bancaria</th>
                <td>{{ $condo->banco ?? '—' }} / {{ $condo->tipo_cuenta ?? '—' }} / {{ $condo->num_cuenta ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="grid">
        <div class="col">
            <h2>Resumen de cobros</h2>
            <table>
                <tr><th>Cuotas y cargos</th><td class="right">${{ number_format($cobros['cargos'], 0, ',', '.') }}</td></tr>
                <tr><th>Intereses por mora</th><td class="right">${{ number_format($cobros['interes'], 0, ',', '.') }}</td></tr>
                <tr><th>Descuentos</th><td class="right">-${{ number_format($cobros['descuentos'], 0, ',', '.') }}</td></tr>
                <tr><th>Pagado en el mes</th><td class="right">-${{ number_format($cobros['pagado'], 0, ',', '.') }}</td></tr>
                <tr><th>Saldo por cobrar</th><td class="right"><strong>${{ number_format($cobros['saldo'], 0, ',', '.') }}</strong></td></tr>
            </table>

            <h2>Pagos por método</h2>
            <table>
                <thead><tr><th>Método</th><th class="right">Monto</th></tr></thead>
                <tbody>
                    @foreach ($pagos_por_metodo as $row)
                        <tr>
                            <td>{{ $row->metodo }}</td>
                            <td class="right">${{ number_format($row->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Total pagos</strong></td>
                        <td class="right"><strong>${{ number_format($total_pagos, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="col">
            <h2>Gastos por categoría</h2>
            <table>
                <thead><tr><th>Categoría</th><th class="right">Monto</th></tr></thead>
                <tbody>
                    @foreach ($gastos_por_cat as $g)
                        <tr>
                            <td>{{ $g->categoria }}</td>
                            <td class="right">${{ number_format($g->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Total gastos</strong></td>
                        <td class="right"><strong>${{ number_format($total_gastos, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>

            <h2>Fondo de reserva</h2>
            <table>
                <tr><th>Abonos</th><td class="right">${{ number_format($fondo_reserva['abonos'], 0, ',', '.') }}</td></tr>
                <tr><th>Cargos</th><td class="right">-${{ number_format($fondo_reserva['cargos'], 0, ',', '.') }}</td></tr>
                <tr><th>Neto</th><td class="right"><strong>${{ number_format($fondo_reserva['neto'], 0, ',', '.') }}</strong></td></tr>
            </table>
        </div>
    </div>

    <h2>Detalle de gastos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Folio</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos_detalle as $g)
                <tr>
                    <td>{{ $g->fecha_emision ?? '—' }}</td>
                    <td>{{ $g->documento_folio ?? '—' }}</td>
                    <td>{{ $g->categoria ?? '—' }}</td>
                    <td>{{ $g->descripcion ?? '' }}</td>
                    <td class="right">${{ number_format($g->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin gastos registrados en el periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($resumen_guardado)
        <p class="muted" style="margin-top:10px">Este periodo está <strong>cerrado</strong> (generado {{ $resumen_guardado->generado_at }}).</p>
    @else
        <p class="muted" style="margin-top:10px">Este periodo aún no está cerrado.</p>
    @endif
@endif
</body>
</html>
