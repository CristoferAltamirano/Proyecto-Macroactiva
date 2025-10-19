<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    /**
     * Muestra el reporte de morosidad.
     */
    public function morosidad()
    {
        // Buscamos todos los cobros pendientes, agrupados por unidad.
        $cobrosPendientes = Cobro::where('estado', 'pendiente')
            ->with('unidad') // Carga la información de la unidad
            ->get()
            ->groupBy('unidad_id'); // Agrupa por el ID de la unidad

        $deudas = [];
        foreach ($cobrosPendientes as $unidadId => $cobros) {
            $deudas[] = [
                'unidad' => $cobros->first()->unidad,
                'total_deuda' => $cobros->sum('monto_total'),
                'meses_adeudados' => $cobros->count(),
            ];
        }

        return view('reportes.morosidad', compact('deudas'));
    }

    /**
     * Muestra el reporte de gastos mensuales.
     */
    public function gastosMensuales(Request $request)
    {
        $request->validate(['periodo' => 'nullable|date_format:Y-m']);

        $periodoSeleccionado = $request->input('periodo')
            ? Carbon::parse($request->input('periodo') . '-01')
            : Carbon::now()->startOfMonth();

        $gastos = Gasto::where('periodo_gasto', $periodoSeleccionado)->get();
        $totalGastos = $gastos->sum('monto');

        return view('reportes.gastos', compact('gastos', 'totalGastos', 'periodoSeleccionado'));
    }
}