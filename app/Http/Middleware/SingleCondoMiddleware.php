<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SingleCondoMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $cid = session('ctx_condo_id');
        if (!$cid) {
            $cid = DB::table('condominio')->orderBy('id_condominio')->value('id_condominio');
            if (!$cid) {
                // Si no hay condominio aún, enviamos a crear uno
                if (!$request->is('admin/condominios')) {
                    return redirect()->route('admin.condos.panel')
                        ->with('warn','Crea el condominio antes de continuar.');
                }
            } else {
                session(['ctx_condo_id' => (int)$cid]);
            }
        }
        return $next($request);
    }
}
