<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibroController extends Controller
{
    /** Compat: por si alguna ruta llama index() */
    public function index(Request $r)
    {
        return $this->panel($r);
    }

    /** Detecta el nombre de la PK si existe (id, id_mov, etc.) */
    private function detectIdColumn(): ?string
    {
        foreach (['id', 'id_mov', 'id_libro', 'id_movimiento', 'id_libro_mov', 'id_libro_movimiento'] as $cand) {
            if (Schema::hasColumn('libro_movimiento', $cand)) {
                return $cand;
            }
        }
        return null;
    }

    /** Helpers de rol */
    private function userHasRole($user, string $role): bool
    {
        if (method_exists($user, 'hasRole')) return $user->hasRole($role);
        return (($user->role ?? null) === $role);
    }
    private function userIsSuper($user): bool
    {
        return $this->userHasRole($user, 'super_admin');
    }

    /** Normaliza IDs a enteros únicos */
    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $v) {
            if ($v === null || $v === '') continue;
            $out[] = (int) $v;
        }
        return array_values(array_unique($out));
    }

    /**
     * Obtiene IDs de condominios permitidos para el usuario desde varias fuentes:
     * - Super Admin => null (sin restricción)
     * - Relación Eloquent (condominios / condominio / condos / ... )
     * - Columna directa en users (id_condominio / condominio_id / id_condo / cod_condominio)
     * - Tablas pivot habituales (admin_user_condominio / user_condominio / condominio_user / asignacion_admin_condo / admin_condo_user / admin_user_condo)
     * - Contexto de sesión (ctx.id_condominio / id_condominio)
     *
     * Devuelve:
     *   null  => sin restricción (super_admin)
     *   []    => ninguno detectado
     *   [ids] => lista de ids permitidos
     */
    private function allowedCondoIds($user): ?array
    {
        if ($this->userIsSuper($user)) {
            return null; // super_admin ve todo
        }

        $ids = [];

        // 0) Contexto de sesión si existiera (no obliga EnsureCondoContext)
        $ctx = session('ctx.id_condominio')
            ?? session('ctx_id_condominio')
            ?? session('id_condominio')
            ?? null;
        if ($ctx) $ids[] = (int) $ctx;

        // 1) Relación Eloquent con nombres comunes
        $relationCandidates = [
            'condominios', 'condominio', 'condos', 'adminCondominios',
            'condominiosAsignados', 'condominiosPermitidos', 'condominios_admin'
        ];
        foreach ($relationCandidates as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    // pluck id_condominio o condominio_id
                    $plucked = $user->{$rel}()->pluck('id_condominio');
                    if ($plucked->isEmpty()) {
                        $plucked = $user->{$rel}()->pluck('condominio_id');
                    }
                    $ids = array_merge($ids, $plucked->all());
                } catch (\Throwable $e) {
                    // ignora si la relación no mapea estas columnas
                }
            }
        }

        // 2) Columna directa en users (fallback)
        foreach (['id_condominio', 'condominio_id', 'id_condo', 'cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) {
                $ids[] = $user->{$col};
                break;
            }
        }

        // 3) Pivots comunes
        $pivotDefs = [
            'admin_user_condominio',
            'user_condominio',
            'condominio_user',
            'asignacion_admin_condo',
            'admin_condo_user',
            'admin_user_condo',
        ];
        $userId = method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? null);

        foreach ($pivotDefs as $table) {
            if (!Schema::hasTable($table) || !$userId) continue;

            $userCols  = ['user_id', 'id_user', 'usuario_id', 'id_usuario'];
            $condoCols = ['id_condominio', 'condominio_id', 'id_condo', 'cod_condominio'];

            // Detecta pareja de columnas válida
            $uCol = null; $cCol = null;
            foreach ($userCols as $uc) {
                if (Schema::hasColumn($table, $uc)) { $uCol = $uc; break; }
            }
            foreach ($condoCols as $cc) {
                if (Schema::hasColumn($table, $cc)) { $cCol = $cc; break; }
            }
            if (!$uCol || !$cCol) continue;

            try {
                $pivotIds = DB::table($table)->where($uCol, $userId)->pluck($cCol)->all();
                $ids = array_merge($ids, $pivotIds);
            } catch (\Throwable $e) {
                // ignora errores de mapeo
            }
        }

        $ids = $this->normalizeIds($ids);

        return $ids; // [] si no se detectó ninguno
    }

    /** Panel */
    public function panel(Request $r)
    {
        $user    = $r->user();
        $allowed = $this->allowedCondoIds($user);
        $idCol   = $this->detectIdColumn();

        // ===== Lista de condominios para el selector =====
        $condosQ = DB::table('condominio')->select('id_condominio', 'nombre')->orderBy('nombre');

        if (is_array($allowed)) {
            if (count($allowed) > 0) {
                $condosQ->whereIn('id_condominio', $allowed);
            } else {
                // Ninguno asignado -> fuerza colección vacía (evita leaks)
                $condosQ->whereRaw('1=0');
            }
        }
        $condos = $condosQ->get();

        // ===== Filtros de la request =====
        $idCondoReq = $r->filled('id_condominio') ? (int) $r->id_condominio : null;
        $desde      = $r->filled('desde') ? $r->desde : null;
        $hasta      = $r->filled('hasta') ? $r->hasta : null;

        // Validación: si hay lista permitida, el req debe pertenecer a ella
        if (is_array($allowed) && $idCondoReq !== null && !in_array($idCondoReq, $allowed, true)) {
            abort(403, 'No tienes permiso para ver este condominio.');
        }

        $q = DB::table('libro_movimiento as l')
            ->leftJoin('cuenta_contable as c', 'c.id_cta_contable', '=', 'l.id_cta_contable')
            ->leftJoin('condominio as k', 'k.id_condominio', '=', 'l.id_condominio')
            ->select(
                'l.fecha',
                'l.id_condominio',
                'k.nombre as condominio',
                'l.id_cta_contable',
                'c.codigo as cta_codigo',
                'c.nombre as cta_nombre',
                'l.debe',
                'l.haber',
                'l.glosa',
                'l.ref_tabla',
                'l.ref_id'
            );

        if ($idCol) {
            $q->addSelect(DB::raw("l.$idCol as id"));
        }

        // Alcance por permiso
        if (is_array($allowed)) {
            if (count($allowed) > 0) {
                $q->whereIn('l.id_condominio', $allowed);
            } else {
                // Nada asignado => no retorna movimientos, pero sin 403
                $q->whereRaw('1=0');
            }
        }

        // Filtros de usuario
        if ($idCondoReq !== null) $q->where('l.id_condominio', $idCondoReq);
        if ($desde) $q->whereDate('l.fecha', '>=', $desde);
        if ($hasta) $q->whereDate('l.fecha', '<=', $hasta);

        $q->orderBy('l.fecha', 'desc');
        if ($idCol) $q->orderBy(DB::raw("l.$idCol"), 'desc');

        $movs = $q->limit(50)->get();

        return view('libro_index', compact('movs', 'condos', 'idCol'));
    }

    /** Export CSV (respeta el mismo alcance de permisos) */
    public function exportCsv(Request $r)
    {
        $user    = $r->user();
        $allowed = $this->allowedCondoIds($user);
        $idCol   = $this->detectIdColumn();

        $idCondoReq = $r->filled('id_condominio') ? (int) $r->id_condominio : null;
        $desde      = $r->filled('desde') ? $r->desde : null;
        $hasta      = $r->filled('hasta') ? $r->hasta : null;

        if (is_array($allowed) && $idCondoReq !== null && !in_array($idCondoReq, $allowed, true)) {
            abort(403, 'No tienes permiso para exportar este condominio.');
        }

        $q = DB::table('libro_movimiento as l')
            ->leftJoin('cuenta_contable as c', 'c.id_cta_contable', '=', 'l.id_cta_contable')
            ->leftJoin('condominio as k', 'k.id_condominio', '=', 'l.id_condominio')
            ->select(
                'l.fecha',
                'k.nombre as condominio',
                'l.id_condominio',
                'c.codigo as cta_codigo',
                'c.nombre as cta_nombre',
                'l.debe',
                'l.haber',
                'l.glosa',
                'l.ref_tabla',
                'l.ref_id'
            );

        if ($idCol) $q->addSelect(DB::raw("l.$idCol as id"));

        if (is_array($allowed)) {
            if (count($allowed) > 0) {
                $q->whereIn('l.id_condominio', $allowed);
            } else {
                $q->whereRaw('1=0');
            }
        }

        if ($idCondoReq !== null) $q->where('l.id_condominio', $idCondoReq);
        if ($desde) $q->whereDate('l.fecha', '>=', $desde);
        if ($hasta) $q->whereDate('l.fecha', '<=', $hasta);

        $q->orderBy('l.fecha', 'desc');
        if ($idCol) $q->orderBy(DB::raw("l.$idCol"), 'desc');

        $rows = $q->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="libro_movimientos.csv"',
        ];

        $columns = $idCol
            ? ['id', 'fecha', 'condominio', 'id_condominio', 'cta_codigo', 'cta_nombre', 'debe', 'haber', 'glosa', 'ref_tabla', 'ref_id']
            : ['fecha', 'condominio', 'id_condominio', 'cta_codigo', 'cta_nombre', 'debe', 'haber', 'glosa', 'ref_tabla', 'ref_id'];

        $callback = function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $columns);
            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = $r->{$col} ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
