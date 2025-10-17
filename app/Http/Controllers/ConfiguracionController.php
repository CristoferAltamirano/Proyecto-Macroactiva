<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    /**
     * Muestra el formulario para editar la configuración.
     */
    public function edit()
    {
        // Obtiene todas las configuraciones y las convierte en un array asociativo clave => valor
        $configuraciones = Configuracion::pluck('valor', 'clave');

        return view('configuracion.edit', compact('configuraciones'));
    }

    /**
     * Actualiza la configuración en la base de datos.
     */
    public function update(Request $request)
    {
        $datos = $request->except('_token');

        foreach ($datos as $clave => $valor) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }

        return redirect()->route('configuracion.edit')->with('success', 'Configuración guardada correctamente.');
    }
}