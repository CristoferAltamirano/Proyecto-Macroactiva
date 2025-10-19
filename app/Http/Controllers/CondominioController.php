<?php

namespace App\Http\Controllers;

use App\Models\Condominio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression as Expr;

class CondominioController extends Controller
{
    public function index()
    {
        $condominios = Condominio::all();
        return view('admin.condominios.index', compact('condominios'));
    }

    public function create()
    {
        return view('admin.condominios.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
        ]);

        Condominio::create($request->all());

        return redirect()->route('condominios.index')
                         ->with('success', 'Condominio created successfully.');
    }

    public function show(Condominio $condominio)
    {
        return view('admin.condominios.show', compact('condominio'));
    }

    public function edit(Condominio $condominio)
    {
        return view('admin.condominios.edit', compact('condominio'));
    }

    public function update(Request $request, Condominio $condominio)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
        ]);

        $condominio->update($request->all());

        return redirect()->route('condominios.index')
                         ->with('success', 'Condominio updated successfully.');
    }

    public function destroy(Condominio $condominio)
    {
        $condominio->delete();

        return redirect()->route('condominios.index')
                         ->with('success', 'Condominio deleted successfully.');
    }
}