<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CondoAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $role = $user->rol ?? ($user->tipo_usuario ?? null);
        $ctx  = (int) ($request->route('id_condominio')
            ?? $request->query('id_condominio')
            ?? session('ctx_condo_id')
            ?? 0);

        /* ===================== SUPER ADMIN ===================== */
        if ($role === 'super_admin') {
            return $next($request);
        }

        /* ========================= ADMIN ======================== */
        if ($role === 'admin') {
            // Si no existe la pivote, no reventamos: usamos contexto o tomamos el primero.
            if (!Schema::hasTable('admin_user_condo')) {
                if ($ctx > 0) {
                    session(['ctx_condo_id' => $ctx]);
                    return $next($request);
                }
                $first = DB::table('condominio')->orderBy('nombre')->value('id_condominio');
                if ($first) {
                    session(['ctx_condo_id' => (int)$first]);
                    return $next($request);
                }
                return response('No tienes condominios asignados. Solicita acceso al super admin.', 403);
            }

            // Con pivote: validar asignaciones del admin
            $uid = $user->id_usuario ?? $user->id ?? 0;
            $allowed = DB::table('admin_user_condo')
                ->where('id_usuario', $uid)
                ->pluck('id_condominio')
                ->map(fn($v) => (int)$v)
                ->filter(fn($v) => $v > 0)
                ->values()
                ->all();

            if (empty($allowed)) {
                return response('No tienes condominios asignados. Solicita acceso al super admin.', 403);
            }

            // Resolver/forzar contexto válido
            if ($ctx === 0 || !in_array($ctx, $allowed, true)) {
                $ctx = (int)$allowed[0];
            }
            session(['ctx_condo_id' => $ctx]);

            return $next($request);
        }

        /* ======== RESIDENTES / COPROPIETARIOS (NO BLOQUEAR) ======== */
        if (in_array($role, ['residente', 'copropietario'], true)) {
            // Si ya hay contexto, seguimos.
            if ($ctx > 0) {
                return $next($request);
            }

            $uid = (int)($user->id_usuario ?? $user->id ?? 0);

            // Intentar deducir condominio desde residencia vigente
            $deducido = DB::table('residencia as r')
                ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                ->where('r.id_usuario', $uid)
                ->whereNull('r.hasta')
                ->orderByDesc('r.id_residencia')
                ->value('g.id_condominio');

            // Si no hay vigente, tomar la más reciente
            if (!$deducido) {
                $deducido = DB::table('residencia as r')
                    ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                    ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                    ->where('r.id_usuario', $uid)
                    ->orderByDesc('r.desde')
                    ->value('g.id_condominio');
            }

            if ($deducido) {
                session(['ctx_condo_id' => (int)$deducido]);
            }
            // Importante: aunque no encontremos condominio, NO bloqueamos.
            // Así pueden acceder a su portal y pagar (Webpay, etc).
            return $next($request);
        }

        // Cualquier otro rol: permitir
        return $next($request);
    }
}
