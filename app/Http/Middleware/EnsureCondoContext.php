<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureCondoContext
{
    /**
     * Devuelve los condominios permitidos al usuario (solo admins).
     */
    private function allowedCondoIdsFor($user): array
    {
        if (!$user) return [];

        $role = $user->rol ?? ($user->tipo_usuario ?? null);

        // super_admin: todos
        if ($role === 'super_admin') {
            return DB::table('condominio')
                ->pluck('id_condominio')
                ->map(fn($v)=>(int)$v)
                ->all();
        }

        // admin: por pivote + ctx (si existiera)
        if ($role === 'admin') {
            $ids = [];
            if (Schema::hasTable('admin_user_condo')) {
                $ids = DB::table('admin_user_condo')
                    ->where('id_usuario', $user->id_usuario ?? $user->id)
                    ->pluck('id_condominio')
                    ->map(fn($v)=>(int)$v)
                    ->all();
            }
            $ctx = session('ctx_condo_id');
            if ($ctx) $ids[] = (int)$ctx;

            return array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));
        }

        // otros roles: no aplican asignaciones explícitas
        return [];
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $role = $user->rol ?? ($user->tipo_usuario ?? null);
        $ctx  = (int) ($request->session()->get('ctx_condo_id') ?? 0);

        /* ===================== SUPER ADMIN ===================== */
        if ($role === 'super_admin') {
            // Si no hay contexto, tomar el primero disponible (sin redirigir)
            if ($ctx <= 0) {
                $first = DB::table('condominio')->orderBy('nombre')->value('id_condominio');
                if ($first) {
                    $request->session()->put('ctx_condo_id', (int)$first);
                }
            }
            return $next($request);
        }

        /* ========================= ADMIN ======================== */
        if ($role === 'admin') {
            $allowed = $this->allowedCondoIdsFor($user);

            // Sin asignaciones: no fijamos ctx (CondoAccess decidirá)
            if (empty($allowed)) {
                $request->session()->forget('ctx_condo_id');
                return $next($request);
            }

            // Si no hay ctx o no pertenece a los permitidos -> fijar el primero permitido
            if ($ctx <= 0 || !in_array($ctx, $allowed, true)) {
                $request->session()->put('ctx_condo_id', (int)$allowed[0]);
            }
            return $next($request);
        }

        /* ========== RESIDENTE / COPROPIETARIO (auto-fijar) ========== */
        if (in_array($role, ['residente','copropietario'], true)) {
            // Si ya hay contexto, no tocamos.
            if ($ctx > 0) {
                return $next($request);
            }

            $userId = (int)($user->id_usuario ?? $user->id ?? 0);

            // 1) Intentar obtener condominio desde residencia VIGENTE
            $ctxFromRes = DB::table('residencia as r')
                ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo') // relación común en tu app
                ->where('r.id_usuario', $userId)
                ->whereNull('r.hasta')
                ->orderByDesc('r.id_residencia')
                ->value('g.id_condominio');

            // 2) Si no hay vigente, tomar la más reciente
            if (!$ctxFromRes) {
                $ctxFromRes = DB::table('residencia as r')
                    ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                    ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                    ->where('r.id_usuario', $userId)
                    ->orderByDesc('r.desde')
                    ->value('g.id_condominio');
            }

            if ($ctxFromRes) {
                $request->session()->put('ctx_condo_id', (int)$ctxFromRes);
            }

            // Importante: aunque no encontremos condominio, NO bloqueamos.
            // Así pueden entrar al portal y pagar (Webpay, etc).
            return $next($request);
        }

        // Cualquier otro rol: seguir normal
        return $next($request);
    }
}
