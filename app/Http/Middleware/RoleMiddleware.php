<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $u = $request->user();
        if (!$u) return redirect()->route('login');
        if ($roles && !in_array($u->tipo_usuario, $roles, true)) abort(403,'No autorizado');
        return $next($request);
    }
}
