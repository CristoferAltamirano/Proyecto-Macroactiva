<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Gasto;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PanelController extends Controller
{
    /**
     * Muestra el panel de control principal con estadísticas clave.
     */
    public function index()
    {
        // 1. Definimos el periodo actual (el mes en curso).
        $periodoActual = Carbon::now()->startOfMonth();

        // 2. Calculamos las estadísticas.
        $totalUnidades = Unidad::count();
        $totalGastosMes = Gasto::where('periodo_gasto', $periodoActual)->sum('monto');
        $cobrosPendientes = Cobro::where('estado', 'pendiente')->count();
        $totalRecaudadoMes = Cobro::where('periodo', $periodoActual)->where('estado', 'pagado')->sum('monto_total');

        // 3. Pasamos todas las variables a la vista.
        return view('dashboard', compact(
            'totalUnidades',
            'totalGastosMes',
            'cobrosPendientes',
            'totalRecaudadoMes',
            'periodoActual'
        ));
    }
}