<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index()
    {
        $gastos = Gasto::latest('fecha_gasto')->paginate(15);
        return view('gastos.index', compact('gastos'));
    }

    public function create()
    {
        return view('gastos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto' => 'required|integer|min:0',
            'tipo' => 'required|in:ordinario,extraordinario',
            'fecha_gasto' => 'required|date',
            'periodo_gasto' => 'required|date',
        ]);

        Gasto::create($request->all());

        return redirect()->route('gastos.index')
                         ->with('success', '¡Gasto registrado exitosamente!');
    }

    /**
     * Muestra el formulario para editar un gasto existente.
     */
    public function edit(Gasto $gasto)
    {
        // Laravel encuentra el gasto por su ID y lo pasa a la vista.
        return view('gastos.edit', compact('gasto'));
    }

    /**
     * Actualiza un gasto existente en la base de datos.
     */
    public function update(Request $request, Gasto $gasto)
    {
        // Usamos las mismas reglas de validación que en 'store'.
        $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto' => 'required|integer|min:0',
            'tipo' => 'required|in:ordinario,extraordinario',
            'fecha_gasto' => 'required|date',
            'periodo_gasto' => 'required|date',
        ]);

        // Actualizamos el gasto con los nuevos datos.
        $gasto->update($request->all());

        return redirect()->route('gastos.index')
                         ->with('success', '¡Gasto actualizado exitosamente!');
    }

    /**
     * Elimina un gasto de la base de datos.
     */
    public function destroy(Gasto $gasto)
    {
        $gasto->delete();

        return redirect()->route('gastos.index')
                         ->with('success', 'Gasto eliminado exitosamente.');
    }
}