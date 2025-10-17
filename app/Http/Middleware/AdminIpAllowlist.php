<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next)
    {
        // Lee lista de IPs desde config('security.admin_ip_allowlist') o ENV SEC_ADMIN_IPS
        $cfg = config('security.admin_ip_allowlist', null);
        if (is_array($cfg)) {
            $ips = $cfg;
        } else {
            $env = (string) env('SEC_ADMIN_IPS', '');
            $ips = $env !== '' ? array_map('trim', explode(',', $env)) : [];
        }

        // Si no hay IPs configuradas, no hace nada (no rompe en local)
        if (empty($ips)) {
            return $next($request);
        }

        // Consideramos "zona admin" por path o por nombre
        $path = ltrim($request->path(), '/');
        $name = $request->route()?->getName();
        $isAdmin = str_starts_with($path, 'admin/') || ($name && str_starts_with($name, 'admin.'));

        if ($isAdmin && !in_array($request->ip(), $ips, true)) {
            abort(403, 'Admin IP not allowed.');
        }

        return $next($request);
    }
}
