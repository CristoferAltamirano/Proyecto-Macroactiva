<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContextController extends Controller
{
    /**
     * Cambia el condominio activo en la sesión.
     * Espera un campo POST: id_condominio (int). Si viene vacío, limpia el contexto.
     */
    public function setCondominio(Request $request)
    {
        $cid = $request->input('id_condominio');

        // Si no envían nada, limpiamos el contexto
        if ($cid === null || $cid === '') {
            $request->session()->forget(['ctx_condo_id', 'ctx_condo_nombre']);
            return back()->with('ok', 'Condominio deseleccionado.');
        }

        $cid = (int) $cid;

        // Verificar que exista en BD
        $existe = DB::table('condominio')->where('id_condominio', $cid)->exists();
        if (!$existe) {
            return back()->with('err', 'El condominio indicado no existe.');
        }

        // (Opcional) Verifica permisos aquí si tienes una tabla de asignaciones
        // p. ej.: admin_user_condo, etc.

        // Guardar en sesión
        $nombre = DB::table('condominio')->where('id_condominio', $cid)->value('nombre');
        $request->session()->put('ctx_condo_id', $cid);
        $request->session()->put('ctx_condo_nombre', $nombre);

        return back()->with('ok', 'Condominio activo: '.$nombre);
    }

    /**
     * (Opcional) Devolver el contexto actual, útil para debug o AJAX.
     */
    public function current(Request $request)
    {
        return response()->json([
            'id'     => $request->session()->get('ctx_condo_id'),
            'nombre' => $request->session()->get('ctx_condo_nombre'),
        ]);
    }
}
