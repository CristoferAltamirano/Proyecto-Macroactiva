<?php

namespace App\Http\Controllers;

use App\Models\Condominio;
use Illuminate\Http\Request;

class CondominioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('super-admin');
        $condominios = Condominio::all();
        return view('condominios.index', compact('condominios'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('super-admin');
        return view('condominios.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('super-admin');
        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
        ]);

        Condominio::create($request->all());

        return redirect()->route('condominios.index')
            ->with('success', 'Condominio created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Condominio $condominio)
    {
        $this->authorize('super-admin');
        return view('condominios.show', compact('condominio'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Condominio $condominio)
    {
        return view('condominios.edit', compact('condominio'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Condominio $condominio)
    {
        $this->authorize('super-admin');
        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
        ]);

        $condominio->update($request->all());

        return redirect()->route('condominios.index')
            ->with('success', 'Condominio updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Condominio $condominio)
    {
        $this->authorize('super-admin');
        $condominio->delete();

        return redirect()->route('condominios.index')
            ->with('success', 'Condominio deleted successfully');
    }
}