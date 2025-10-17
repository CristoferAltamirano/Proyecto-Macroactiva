<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Muestra la página principal de la aplicación.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Aquí dentro va TODA la lógica que antes tenías en la ruta.
        // Por ejemplo, si necesitas obtener datos de la base de datos para la vista,
        // lo harías aquí.

        // Por ahora, solo devolveremos la vista.
        return view('login');
    }
}