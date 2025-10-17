<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnidadController extends Controller
{
    public function index()
    {
        $unidades = Unidad::latest()->paginate(10);
        return view('unidades.index', compact('unidades'));
    }

    public function create()
    {
        return view('unidades.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'numero' => 'required|string|max:50',
            'residente' => 'required|string|max:255',
            'propietario' => 'required|string|max:255',
            'email' => 'required|email|unique:unidades,email',
            'telefono' => 'nullable|string|max:20',
            'prorrateo' => 'required|numeric|min:0',
            'estado' => 'required|in:Activo,Inactivo',
        ]);

        Unidad::create($request->all());

        return redirect()->route('unidades.index')
                         ->with('success', '¡Unidad creada exitosamente!');
    }

    public function edit(Unidad $unidad)
    {
        return view('unidades.edit', compact('unidad'));
    }

    public function update(Request $request, Unidad $unidad)
    {
        $request->validate([
            'numero' => 'required|string|max:50',
            'residente' => 'required|string|max:255',
            'propietario' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('unidades')->ignore($unidad->id)],
            'telefono' => 'nullable|string|max:20',
            'prorrateo' => 'required|numeric|min:0',
            'estado' => 'required|in:Activo,Inactivo',
        ]);

        $unidad->update($request->all());

        return redirect()->route('unidades.index')
                         ->with('success', '¡Unidad actualizada exitosamente!');
    }

    public function destroy(Unidad $unidad)
    {
        $unidad->delete();

        return redirect()->route('unidades.index')
                         ->with('success', 'Unidad eliminada exitosamente.');
    }
}