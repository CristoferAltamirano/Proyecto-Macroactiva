<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCondoGuard
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $role = $user->rol ?? ($user->tipo_usuario ?? null);
        if ($role === 'super_admin') {
            return $next($request); // sin restricciones
        }

        if ($role !== 'admin') {
            return $next($request); // copropietario/residente, otro flujo
        }

        // 1) Resolver condominio de contexto
        $ctx = session('ctx_condo_id');
        if (!$ctx) {
            // buscar primer condominio asignado al admin
            // (ajusta el nombre de tu tabla pivote si difiere)
            $first = DB::table('admin_user_condo')
                ->where('id_usuario', $user->id_usuario ?? $user->id)
                ->orderBy('id_condominio')
                ->value('id_condominio');

            if (!$first) {
                abort(403, 'No tienes condominios asignados.');
            }

            session(['ctx_condo_id' => (int)$first]);
            $ctx = (int)$first;
        }

        // 2) Verificar que el admin pertenezca al condominio de contexto
        $assigned = DB::table('admin_user_condo')
            ->where('id_usuario', $user->id_usuario ?? $user->id)
            ->where('id_condominio', $ctx)
            ->exists();

        if (!$assigned) {
            abort(403, 'No tienes acceso a este condominio.');
        }

        // 3) Blindar cualquier id_condominio entrante (query o form)
        //    Forzamos SIEMPRE al contexto
        $request->query->set('id_condominio', $ctx);
        $request->merge(['id_condominio' => $ctx]);

        // También dejamos disponible como atributo por si quieres leerlo así:
        $request->attributes->set('ctx_condo_id', $ctx);

        return $next($request);
    }
}
