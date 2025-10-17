<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Verifica si el usuario está logueado Y si su rol coincide con el requerido.
        if (!Auth::check() || Auth::user()->role !== $role) {
            // 2. Si no cumple, lo echamos. Abort(403) es el código para "Acceso Prohibido".
            abort(403, 'Acceso no autorizado.');
        }

        // 3. Si cumple, lo dejamos pasar a la siguiente petición.
        return $next($request);
    }
}