<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Obtiene el condominio efectivo para las operaciones.
     *
     * Reglas:
     * - super_admin: puede pasar un id_condominio explícito; si no viene, usa el del contexto (session('ctx_condo_id')).
     * - admin: siempre fuerza el id_condominio del contexto (session('ctx_condo_id')), ignorando el recibido.
     * - otros (portal): retorna null.
     *
     * @param  int|null  $fromRequest  id_condominio recibido del request (si aplica)
     * @return int|null  id_condominio efectivo o null
     */
    protected function effectiveCondoId(?int $fromRequest = null): ?int
    {
        $u = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);

        if ($role === 'super_admin') {
            return $fromRequest ?? session('ctx_condo_id');
        }

        if ($role === 'admin') {
            return session('ctx_condo_id'); // fuerza contexto para admins de condominio
        }

        // Portal (copropietario/residente) u otros
        return null;
    }
}
