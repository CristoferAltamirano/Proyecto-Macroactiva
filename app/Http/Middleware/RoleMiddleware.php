<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();
        $roles = explode('|', $roles);

        foreach ($roles as $role) {
            // Si el usuario tiene el rol, permite el acceso.
            if ($user->role === $role) {
                return $next($request);
            }
        }

        // Si el bucle termina, el usuario no tiene ninguno de los roles requeridos.
        abort(403, 'Acceso no autorizado.');
    }
}