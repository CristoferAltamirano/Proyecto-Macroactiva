<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Pago;
use App\Services\ContabilidadService;
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
     * Registra un pago para un cobro y actualiza su estado.
     */
    public function registrarPago(Cobro $cobro)
    {
        // 1. Creamos el registro del pago
        $pago = Pago::create([
            'cobro_id' => $cobro->id,
            'unidad_id' => $cobro->unidad_id,
            'monto' => $cobro->monto_total,
            'fecha_pago' => now(),
            'metodo_pago' => 'manual',
        ]);

        // 2. Cambiamos el estado del cobro.
        $cobro->estado = 'pagado';
        $cobro->save();

        // 3. Registramos el asiento contable
        // NOTA: Esta llamada funciona para pagos manuales. Cuando se implemente el pago online (ej. Webpay),
        // el controlador que reciba la confirmación del pago deberá crear el objeto Pago y
        // llamar a este mismo servicio para asegurar la consistencia contable.
        (new ContabilidadService())->registrarPago($pago);

        // 4. Redirigimos de vuelta con un mensaje de éxito.
        return back()->with('success', '¡Pago registrado y contabilizado exitosamente para la unidad ' . $cobro->unidad->numero . '!');
    }
}