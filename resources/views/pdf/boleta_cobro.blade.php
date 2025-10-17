<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Cobro</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
        .container { width: 100%; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: #0056b3; }
        .header p { margin: 0; font-size: 14px; }
        .details { margin-bottom: 20px; border: 1px solid #eee; padding: 15px; border-radius: 5px; }
        .details table { width: 100%; }
        .summary, .expenses { margin-bottom: 25px; }
        h2 { font-size: 16px; border-bottom: 2px solid #0056b3; padding-bottom: 5px; margin-bottom: 10px; color: #0056b3;}
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; }
        th { background-color: #f4f7f6; }
        .expenses table td, .expenses table th { border-bottom: 1px solid #eee; }
        .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .text-right { text-align: right; }
        .footer { text-align: center; font-size: 10px; color: #777; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Macroactiva</h1>
            <p>Gestión de Condominios</p>
        </div>

        <div class="details">
            <table>
                <tr>
                    <td><strong>Unidad:</strong> {{ $cobro->unidad->numero }}</td>
                    <td><strong>Periodo:</strong> {{ \Carbon\Carbon::parse($cobro->periodo)->translatedFormat('F Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Propietario:</strong> {{ $cobro->unidad->propietario }}</td>
                    <td><strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        <div class="summary">
            <h2>Resumen del Cobro</h2>
            <table>
                <tr>
                    <td>Gasto Común Ordinario</td>
                    <td class="text-right">${{ number_format($cobro->monto_gasto_comun, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Fondo de Reserva</td>
                    <td class="text-right">${{ number_format($cobro->monto_fondo_reserva, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Multas e Intereses</td>
                    <td class="text-right">${{ number_format($cobro->monto_multas, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total a Pagar</td>
                    <td class="text-right">${{ number_format($cobro->monto_total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <div class="expenses">
            <h2>Desglose de Gastos Comunes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gastosDelPeriodo as $gasto)
                        <tr>
                            <td>{{ $gasto->descripcion }}</td>
                            <td class="text-right">${{ number_format($gasto->monto, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No hay gastos detallados para este periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total Gastos del Periodo</td>
                        <td class="text-right">${{ number_format($gastosDelPeriodo->sum('monto'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <p>Este documento es una boleta informativa de cobro. Para realizar su pago, por favor ingrese al Portal de Residentes.</p>
            <p>Generado el {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>