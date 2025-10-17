<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditoriaController extends Controller
{
    /* ==== Helpers de rol ==== */
    private function userHasRole($user, string $role): bool
    {
        if (method_exists($user, 'hasRole')) return $user->hasRole($role);
        $raw = $user->role ?? $user->rol ?? $user->tipo ?? $user->tipo_usuario ?? null;
        return is_string($raw) && mb_strtolower($raw) === mb_strtolower($role);
    }
    private function userIsSuper($user): bool
    {
        return $this->userHasRole($user, 'super_admin');
    }

    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $v) {
            if ($v === null || $v === '') continue;
            $out[] = (int) $v;
        }
        return array_values(array_unique($out));
    }

    /** Descubre IDs de condominio permitidos (relación, columna en users, pivots, sesión). */
    private function allowedCondoIds($user): ?array
    {
        if ($this->userIsSuper($user)) return null;

        $ids = [];
        // Sesión (si la usas)
        $ctx = session('ctx.id_condominio')
            ?? session('ctx_id_condominio')
            ?? session('ctx_condo_id')
            ?? session('id_condominio')
            ?? null;
        if ($ctx) $ids[] = (int) $ctx;

        // Relaciones típicas en User
        foreach (['condominios','condominio','condos','adminCondominios','condominiosAsignados','condominiosPermitidos','condominios_admin'] as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    $pl = $user->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $user->{$rel}()->pluck('condominio_id');
                    $ids = array_merge($ids, $pl->all());
                } catch (\Throwable $e) {}
            }
        }

        // Columnas directas en users
        foreach (['id_condominio','condominio_id','id_condo','cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) { $ids[] = $user->{$col}; break; }
        }

        // Pivots comunes
        $pivots = ['admin_user_condominio','user_condominio','condominio_user','asignacion_admin_condo','admin_condo_user','admin_user_condo'];
        $userId = method_exists($user,'getKey') ? $user->getKey() : ($user->id ?? null);
        foreach ($pivots as $table) {
            if (!$userId || !Schema::hasTable($table)) continue;
            $userCols  = ['user_id','id_user','usuario_id','id_usuario'];
            $condoCols = ['id_condominio','condominio_id','id_condo','cod_condominio'];
            $uCol = null; foreach ($userCols as $uc) if (Schema::hasColumn($table,$uc)) { $uCol=$uc; break; }
            $cCol = null; foreach ($condoCols as $cc) if (Schema::hasColumn($table,$cc)) { $cCol=$cc; break; }
            if (!$uCol || !$cCol) continue;
            try { $ids = array_merge($ids, DB::table($table)->where($uCol,$userId)->pluck($cCol)->all()); } catch (\Throwable $e) {}
        }

        return $this->normalizeIds($ids);
    }

    /* ==== Resolución de condominio por entidad (si auditoria no tiene id_condominio) ==== */

    private function fetchCondoIdDirect(string $table, string $pkCol, int $id, string $condoCol = 'id_condominio'): ?int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table,$pkCol)) return null;
        if (!Schema::hasColumn($table,$condoCol)) return null;
        $val = DB::table($table)->where($pkCol,$id)->value($condoCol);
        return $val ? (int)$val : null;
    }

    private function fetchCondoIdViaFK(
        string $fromTable, string $fromPk, int $id,
        string $fkCol,
        string $toTable, string $toPk, string $toCondoCol = 'id_condominio'
    ): ?int {
        if (!Schema::hasTable($fromTable) || !Schema::hasColumn($fromTable,$fromPk) || !Schema::hasColumn($fromTable,$fkCol)) return null;
        $fk = DB::table($fromTable)->where($fromPk,$id)->value($fkCol);
        if (!$fk) return null;
        return $this->fetchCondoIdDirect($toTable, $toPk, (int)$fk, $toCondoCol);
    }

    /** Intenta deducir id_condominio por entidad (nombre flexible). */
    private function inferCondoId(string $entidad, int $entidadId): ?int
    {
        $e = mb_strtolower($entidad);

        // Mapea entidades directas (tabla, pk, col_condo)
        $direct = [
            'grupo'             => ['grupo','id_grupo','id_condominio'],
            'gasto'             => ['gasto','id_gasto','id_condominio'],
            'pago'              => ['pago','id_pago','id_condominio'],
            'trabajador'        => ['trabajador','id_trabajador','id_condominio'],
            'contrato'          => ['contrato','id_contrato','id_condominio'],
            'unidad'            => ['unidad','id_unidad','id_condominio'],
            'residencia'        => ['residencia','id_residencia','id_condominio'],
            'copropietario'     => ['copropietario','id_copropietario','id_condominio'],
            'fondo_reserva_mov' => ['fondo_reserva_mov','id_fondo_reserva_mov','id_condominio'],
            'prorrateo_regla'   => ['prorrateo_regla','id_prorrateo_regla','id_condominio'],
            // agrega acá otras que en tu BD tengan id_condominio directo
        ];
        if (isset($direct[$e])) {
            [$t,$pk,$ck] = $direct[$e];
            $v = $this->fetchCondoIdDirect($t,$pk,$entidadId,$ck);
            if ($v !== null) return $v;
        }

        // Vías alternativas comunes (cuando no existe id_condominio directo)
        // pago -> unidad -> condominio
        if ($e === 'pago') {
            // pago.id_unidad -> unidad.id_condominio
            $via = $this->fetchCondoIdViaFK('pago','id_pago',$entidadId,'id_unidad','unidad','id_unidad','id_condominio');
            if ($via !== null) return $via;
        }

        // contrato -> trabajador -> condominio
        if ($e === 'contrato') {
            $via = $this->fetchCondoIdViaFK('contrato','id_contrato',$entidadId,'id_trabajador','trabajador','id_trabajador','id_condominio');
            if ($via !== null) return $via;
        }

        // residencia -> unidad -> condominio
        if ($e === 'residencia') {
            $via = $this->fetchCondoIdViaFK('residencia','id_residencia',$entidadId,'id_unidad','unidad','id_unidad','id_condominio');
            if ($via !== null) return $via;
        }

        // gasto -> unidad (si existiera) -> condominio (fallback adicional)
        if ($e === 'gasto') {
            $via = $this->fetchCondoIdViaFK('gasto','id_gasto',$entidadId,'id_unidad','unidad','id_unidad','id_condominio');
            if ($via !== null) return $via;
        }

        return null; // no se pudo inferir
    }

    public function index(Request $r)
    {
        $user       = $r->user();
        $allowed    = $this->allowedCondoIds($user); // null => sin restricción
        $entidad    = $r->query('entidad');
        $idCondoReq = $r->filled('id_condominio') ? (int)$r->id_condominio : null;

        if (is_array($allowed) && $idCondoReq !== null && !in_array($idCondoReq, $allowed, true)) {
            $idCondoReq = null; // ignora intentos fuera de alcance
        }

        $hasIdCondoCol = Schema::hasColumn('auditoria','id_condominio');
        $orderCol = Schema::hasColumn('auditoria','id_auditoria') ? 'id_auditoria'
                 : (Schema::hasColumn('auditoria','created_at') ? 'created_at' : null);

        // ===== 1) Trae registros base (limit 200), con los filtros que sí se puedan hacer en SQL =====
        $q = DB::table('auditoria');
        if ($entidad) $q->where('entidad',$entidad);

        if ($hasIdCondoCol) {
            if (is_array($allowed)) {
                if (count($allowed) > 0) $q->whereIn('id_condominio',$allowed);
                else $q->whereRaw('1=0');
            }
            if ($idCondoReq !== null) $q->where('id_condominio',$idCondoReq);
        }

        if ($orderCol) $q->orderByDesc($orderCol);
        $reg = $q->limit(200)->get();

        // ===== 2) Si auditoria NO tiene id_condominio, filtra en PHP infiriendo por entidad =====
        if (!$hasIdCondoCol && is_array($allowed)) {
            if (count($allowed) === 0) {
                $reg = collect(); // no asignado -> nada
            } else {
                $allowSet = array_flip($allowed); // lookup O(1)

                $reg = $reg->filter(function ($a) use ($allowSet, $idCondoReq) {
                    $eid = (int)($a->entidad_id ?? 0);
                    $ent = (string)($a->entidad ?? '');
                    if ($eid <= 0 || $ent === '') return false;

                    // Si el registro ya trae id_condominio, úsalo
                    if (property_exists($a,'id_condominio') && $a->id_condominio !== null) {
                        $cid = (int)$a->id_condominio;
                    } else {
                        // Inferir desde la entidad
                        $cid = $this->inferCondoId($ent, $eid) ?? 0;
                    }

                    if ($cid === 0) return false;                  // no se pudo inferir
                    if (!isset($allowSet[$cid])) return false;     // fuera de alcance
                    if ($idCondoReq !== null && $cid !== $idCondoReq) return false; // filtrar por request
                    // Adjunta el condo id inferido para la vista si no venía
                    if (!property_exists($a,'id_condominio')) $a->id_condominio = $cid;
                    return true;
                })->values();
            }
        }

        // ===== 3) Entidades para el filtro (desde lo que el usuario SÍ puede ver) =====
        $entidades = $reg->pluck('entidad')->filter()->unique()->values();

        // ===== 4) Lista de condominios para el select =====
        if ($this->userIsSuper($user)) {
            $condos = DB::table('condominio')->select('id_condominio','nombre')->orderBy('nombre')->get();
        } else {
            if (is_array($allowed) && count($allowed) > 0) {
                $condos = DB::table('condominio')->select('id_condominio','nombre')
                    ->whereIn('id_condominio',$allowed)->orderBy('nombre')->get();
            } else {
                $condos = collect();
            }
        }

        return view('audit_panel', compact('reg','entidades','entidad','condos'));
    }
}
