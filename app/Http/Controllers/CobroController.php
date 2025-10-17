<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CobroController extends Controller
{
    /**
     * Muestra una lista de los cobros generados, filtrados por periodo.
     */
    public function index(Request $request)
    {
        $request->validate(['periodo' => 'nullable|date_format:Y-m']);

        $periodoSeleccionado = $request->input('periodo')
            ? Carbon::parse($request->input('periodo') . '-01')
            : Carbon::now()->startOfMonth();

        $cobros = Cobro::where('periodo', $periodoSeleccionado)
                       ->with('unidad')
                       ->get();

        return view('cobros.index', [
            'cobros' => $cobros,
            'periodoSeleccionado' => $periodoSeleccionado,
        ]);
    }

    /**
     * Cambia el estado de un cobro a 'pagado'.
     */
    public function registrarPago(Cobro $cobro)
    {
        // 1. Cambiamos el estado del cobro.
        $cobro->estado = 'pagado';
        
        // 2. Guardamos el cambio en la base de datos.
        $cobro->save();

        // 3. Redirigimos de vuelta con un mensaje de éxito.
        return back()->with('success', '¡Pago registrado exitosamente para la unidad ' . $cobro->unidad->numero . '!');
    }
}