<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\Gasto;
use App\Models\Unidad;
use App\Services\ContabilidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GeneracionCobroController extends Controller
{
    /**
     * Muestra la vista para iniciar la generación de cobros.
     */
    public function index()
    {
        return view('generacion.index');
    }

    /**
     * Realiza el cálculo y la generación masiva de cobros para un periodo.
     */
    public function generar(Request $request)
    {
        // 1. Validación: Nos aseguramos de recibir un periodo válido.
        $request->validate(['periodo' => 'required|date_format:Y-m']);
        $periodo = Carbon::parse($request->periodo . '-01')->startOfMonth();

        // 2. Seguridad: Verificamos que no se hayan generado cobros para este mes.
        if (Cobro::where('periodo', $periodo)->exists()) {
            return back()->withErrors(['periodo' => 'Los cobros para este periodo ya fueron generados.']);
        }

        // 3. Cálculo: Sumamos todos los gastos del periodo.
        $totalGastosOrdinarios = Gasto::where('periodo_gasto', $periodo)->where('tipo', 'ordinario')->sum('monto');

        // Según la ley chilena, el fondo de reserva se calcula sobre los gastos ordinarios.
        // Usaremos un 10% como ejemplo, esto debería ser configurable.
        $montoFondoReservaTotal = $totalGastosOrdinarios * 0.10;

        // 4. Obtenemos todas las unidades activas para generarles el cobro.
        $unidadesActivas = Unidad::where('estado', 'Activo')->get();

        if ($unidadesActivas->isEmpty()) {
             return back()->withErrors(['periodo' => 'No hay unidades activas para generar cobros.']);
        }

        // 5. ¡La Magia! Recorremos cada unidad y creamos su cobro.
        $cobrosGenerados = collect();
        foreach ($unidadesActivas as $unidad) {
            $montoGastoComunUnidad = $totalGastosOrdinarios * $unidad->prorrateo;
            $montoFondoReservaUnidad = $montoFondoReservaTotal * $unidad->prorrateo;

            $cobro = Cobro::create([
                'unidad_id' => $unidad->id,
                'periodo' => $periodo,
                'monto_gasto_comun' => round($montoGastoComunUnidad),
                'monto_fondo_reserva' => round($montoFondoReservaUnidad),
                'monto_total' => round($montoGastoComunUnidad + $montoFondoReservaUnidad), // Sumaremos multas después
                'estado' => 'pendiente',
            ]);
            $cobrosGenerados->push($cobro);
        }

        // 6. Registrar el asiento contable para la generación masiva
        if ($cobrosGenerados->isNotEmpty()) {
            // Asumimos que todos los cobros de un lote son del mismo condominio.
            // Una mejora futura sería agrupar por condominio si la app es multi-condominio.
            $idCondominio = $unidadesActivas->first()->grupo->id_condominio;
            (new ContabilidadService())->registrarGeneracionCobros($cobrosGenerados, $idCondominio);
        }

        // 6. Redirección con mensaje de éxito.
        return redirect()->route('generacion.index')
                         ->with('success', '¡Cobros para ' . $periodo->translatedFormat('F Y') . ' generados exitosamente para ' . $unidadesActivas->count() . ' unidades!');
    }
}