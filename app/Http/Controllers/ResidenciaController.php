<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResidenciaController extends Controller
{
    /* ======================================================================
     * ALCANCE POR CONDOMINIO (robusto y compatible)
     * ====================================================================== */

    /**
     * Devuelve los id_condominio permitidos para el usuario actual.
     * - super_admin: sin restricción (lo detectamos con rol)
     * - admin: detecta desde relaciones comunes, columnas en users, pivots y contexto
     *
     * NOTA: mantenemos la firma y comportamiento (array de ints) para no romper nada.
     */
    private function allowedCondoIds(): array
    {
        $u     = auth()->user();
        $role  = $u->rol ?? ($u->tipo_usuario ?? $u->role ?? null);
        $isSuper = false;

        // Spatie u otros
        if (method_exists($u, 'hasRole')) {
            $isSuper = $u->hasRole('super_admin');
        } else {
            $isSuper = ($role === 'super_admin');
        }

        if ($isSuper) {
            // Sin restricción: devolvemos todos los IDs (manteniendo tu contrato de retorno array<int>)
            return DB::table('condominio')
                ->pluck('id_condominio')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $ids = [];

        // Relación en el modelo User (si existe) — probamos varios nombres
        foreach (['condominios', 'condominio', 'condos', 'adminCondominios', 'condominiosAsignados', 'condominiosPermitidos', 'condominios_admin'] as $rel) {
            if (method_exists($u, $rel)) {
                try {
                    $pl = $u->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $u->{$rel}()->pluck('condominio_id');
                    $ids = array_merge($ids, $pl->all());
                } catch (\Throwable $e) {}
            }
        }

        // Columnas comunes en users
        foreach (['id_condominio', 'condominio_id', 'id_condo', 'cod_condominio'] as $col) {
            if (isset($u->{$col}) && $u->{$col} !== null) {
                $ids[] = (int) $u->{$col};
                break;
            }
        }

        // Pivots comunes (usamos DB::getSchemaBuilder() como en tu código)
        $schema = DB::getSchemaBuilder();
        $userId = $u->id_usuario ?? $u->id ?? null;
        foreach (['admin_user_condo','admin_user_condominio','user_condominio','condominio_user','asignacion_admin_condo','admin_condo_user'] as $pivot) {
            if (!$userId || !$schema->hasTable($pivot)) continue;

            // Detecta columnas
            $cols = array_map('strtolower', $schema->getColumnListing($pivot));
            $uCol = null; foreach (['user_id','id_user','usuario_id','id_usuario'] as $c) if (in_array($c,$cols,true)) { $uCol=$c; break; }
            $cCol = null; foreach (['id_condominio','condominio_id','id_condo','cod_condominio'] as $c) if (in_array($c,$cols,true)) { $cCol=$c; break; }
            if (!$uCol || !$cCol) continue;

            try {
                $ids = array_merge($ids, DB::table($pivot)->where($uCol, $userId)->pluck($cCol)->all());
            } catch (\Throwable $e) {}
        }

        // Contexto en sesión (varios posibles nombres)
        $ctx = session('ctx.id_condominio')
            ?? session('ctx_id_condominio')
            ?? session('ctx_condo_id')
            ?? session('ctx_condominio_id')
            ?? session('id_condominio')
            ?? null;
        if ($ctx) $ids[] = (int) $ctx;

        // Normalizar
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        return $ids;
    }

    /** ID del condominio activo: GET (id_condominio) > sesión. */
    private function condoActivo(Request $r): ?int
    {
        $id = (int) (
            $r->query('id_condominio')
            ?: (session('ctx.id_condominio')
                ?? session('ctx_id_condominio')
                ?? session('ctx_condo_id')
                ?? session('ctx_condominio_id')
                ?? session('id_condominio')
                ?? 0)
        );
        return $id > 0 ? $id : null;
    }

    /**
     * Detecta la columna que relaciona UNIDAD con CONDOMINIO.
     * Usa DB::getSchemaBuilder() para no depender de la facade Schema.
     */
    private function unidadCondoColumn(): ?string
    {
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('unidad')) return null;

        $cols = array_map('strtolower', $schema->getColumnListing('unidad'));
        foreach (['id_condominio','condominio_id','id_condo'] as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null; // si no existe relación directa, usaremos unidad -> grupo -> condominio
    }

    /**
     * Usuarios elegibles para crear residencia en el CONDOMINIO indicado:
     * - tipo_usuario ∈ {residente, copropietario}
     * - activo = 1
     * - SIN residencia vigente (r.hasta IS NULL) en ese condominio
     */
    private function usuariosElegibles(?int $idCondo): \Illuminate\Support\Collection
    {
        $uCondoCol = $this->unidadCondoColumn();

        // Usuarios con residencia vigente en el condominio activo
        $ocupadosQ = DB::table('residencia as r')
            ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
            ->whereNull('r.hasta');

        if ($idCondo) {
            if ($uCondoCol) {
                $ocupadosQ->where("u.$uCondoCol", $idCondo);
            } else {
                // vía grupo si existe
                $schema = DB::getSchemaBuilder();
                if ($schema->hasTable('grupo')) {
                    $ocupadosQ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                              ->where('g.id_condominio', $idCondo);
                } else {
                    // No podemos saber el condominio -> devolvemos elegibles "globales"
                }
            }
        }

        $ocupados = $ocupadosQ->pluck('r.id_usuario')->all();

        // Elegibles = residentes/copropietarios activos y NO en $ocupados
        return DB::table('usuario')
            ->whereIn('tipo_usuario', ['residente','copropietario'])
            ->where('activo', 1)
            ->when(!empty($ocupados), fn($q) => $q->whereNotIn('id_usuario', $ocupados))
            ->orderBy('nombres')->orderBy('apellidos')
            ->select('id_usuario','nombres','apellidos','email','tipo_usuario')
            ->get();
    }

    /* ======================================================================
     * PANEL
     * ====================================================================== */

    public function index(Request $r)
    {
        $u         = auth()->user();
        $role      = $u->rol ?? ($u->tipo_usuario ?? $u->role ?? null);
        $isSuper   = method_exists($u,'hasRole') ? $u->hasRole('super_admin') : ($role === 'super_admin');

        $allowed   = $this->allowedCondoIds();   // array<int> (vacío si no tiene)
        $idCondo   = $this->condoActivo($r);
        $uCondoCol = $this->unidadCondoColumn();
        $schema    = DB::getSchemaBuilder();
        $hasGrupo  = $schema->hasTable('grupo') && in_array('id_condominio', array_map('strtolower', $schema->getColumnListing('grupo')), true);

        /* ===== Últimas residencias (limit 80) ===== */
        $ult = DB::table('residencia as r')
            ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
            ->leftJoin('usuario as usu', 'usu.id_usuario', '=', 'r.id_usuario');

        // Filtro por alcance
        if (!$isSuper) {
            if (empty($allowed)) {
                $ult->whereRaw('1=0');
            } else {
                if ($uCondoCol) {
                    $ult->whereIn("u.$uCondoCol", $allowed);
                } elseif ($hasGrupo) {
                    $ult->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                        ->whereIn('g.id_condominio', $allowed);
                } else {
                    // No hay manera de inferir condominio -> ocultamos por seguridad
                    $ult->whereRaw('1=0');
                }
            }
        }

        // Filtro por condominio activo (si lo hay)
        if ($idCondo) {
            if ($uCondoCol) {
                $ult->where("u.$uCondoCol", $idCondo);
            } elseif ($hasGrupo) {
                $ult->leftJoin('grupo as g2', 'g2.id_grupo', '=', 'u.id_grupo')
                    ->where('g2.id_condominio', $idCondo);
            }
        }

        $ult = $ult->select(
                'r.*',
                'u.codigo as unidad',
                'usu.email',
                'usu.nombres',
                'usu.apellidos'
            )
            ->orderByDesc('r.id_residencia')
            ->limit(80)
            ->get();

        /* ===== Unidades para el combo ===== */
        $unidades = DB::table('unidad as u');

        if ($uCondoCol) {
            if ($idCondo) $unidades->where("u.$uCondoCol", $idCondo);
            if (!$isSuper && empty($idCondo)) {
                if (empty($allowed)) $unidades->whereRaw('1=0');
                else $unidades->whereIn("u.$uCondoCol", $allowed);
            }
        } elseif ($hasGrupo) {
            $unidades->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo');
            if ($idCondo) $unidades->where('g.id_condominio', $idCondo);
            if (!$isSuper && empty($idCondo)) {
                if (empty($allowed)) $unidades->whereRaw('1=0');
                else $unidades->whereIn('g.id_condominio', $allowed);
            }
        } else {
            // No hay forma de saber condominio de una unidad -> fail-safe para admins
            if (!$isSuper) $unidades->whereRaw('1=0');
        }

        $unidades = $unidades
            ->orderBy('u.id_unidad')
            ->limit(300)
            ->get(['u.id_unidad','u.codigo']);

        /* ===== (legacy) Usuarios vinculados dentro del alcance ===== */
        $usuariosBase = DB::table('usuario as usu')
            ->whereIn('usu.tipo_usuario', ['copropietario', 'residente']);

        if (!$isSuper) {
            if (empty($allowed)) {
                $usuarios = collect();
            } else {
                $vinc = DB::table('unidad as u');
                if ($uCondoCol) {
                    $vinc->whereIn("u.$uCondoCol", $allowed);
                } elseif ($hasGrupo) {
                    $vinc->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                         ->whereIn('g.id_condominio', $allowed);
                } else {
                    $usuarios = collect();
                    goto despuesUsuarios;
                }
                $idsUsuariosVinculados = $vinc
                    ->leftJoin('copropietario as cp', 'cp.id_unidad', '=', 'u.id_unidad')
                    ->leftJoin('residencia as r', 'r.id_unidad', '=', 'u.id_unidad')
                    ->select(DB::raw('COALESCE(cp.id_usuario, r.id_usuario) as id_usuario'))
                    ->whereNotNull(DB::raw('COALESCE(cp.id_usuario, r.id_usuario)'))
                    ->distinct();

                $usuarios = $usuariosBase
                    ->whereIn('usu.id_usuario', $idsUsuariosVinculados)
                    ->orderBy('usu.nombres')
                    ->limit(200)
                    ->get();
            }
        } else {
            $usuarios = $usuariosBase->orderBy('usu.nombres')->limit(200)->get();
        }
        despuesUsuarios:

        /* ===== Usuarios elegibles (sin residencia vigente en el condominio activo) ===== */
        $usuariosElegibles = $this->usuariosElegibles($idCondo);

        return view('residencias_index', [
            'ult'               => $ult,
            'usuarios'          => $usuarios,          // compat
            'unidades'          => $unidades,
            'usuariosElegibles' => $usuariosElegibles, // recomendado por la vista
        ]);
    }

    /* ======================================================================
     * STORE
     * ====================================================================== */
    public function store(Request $r)
    {
        $u       = auth()->user();
        $role    = $u->rol ?? ($u->tipo_usuario ?? $u->role ?? null);
        $isSuper = method_exists($u,'hasRole') ? $u->hasRole('super_admin') : ($role === 'super_admin');
        $allowed = $this->allowedCondoIds();
        $schema  = DB::getSchemaBuilder();
        $uCondoCol = $this->unidadCondoColumn();
        $hasGrupo  = $schema->hasTable('grupo') && in_array('id_condominio', array_map('strtolower', $schema->getColumnListing('grupo')), true);

        $d = $r->validate([
            'id_unidad'   => ['required', 'integer'],
            'id_usuario'  => ['required', 'integer'],
            'origen'      => ['required', 'in:propietario,arrendatario'],
            'desde'       => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:200'],
        ]);

        // Seguridad: validar que la unidad pertenezca a un condominio permitido
        if (!$isSuper) {
            if (empty($allowed)) {
                return back()->with('err', 'No tienes condominios asignados.')->withInput();
            }

            $okUnidadQ = DB::table('unidad as u')->where('u.id_unidad', (int) $d['id_unidad']);
            if ($uCondoCol) {
                $okUnidadQ->whereIn("u.$uCondoCol", $allowed);
            } elseif ($hasGrupo) {
                $okUnidadQ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                          ->whereIn('g.id_condominio', $allowed);
            } else {
                return back()->with('err', 'No estás autorizado para operar en esa unidad.')->withInput();
            }

            $okUnidad = $okUnidadQ->exists();
            if (!$okUnidad) {
                return back()->with('err', 'No estás autorizado para operar en esa unidad.')->withInput();
            }
        }

        // Cierra residencia vigente para el mismo usuario/unidad si existiera
        DB::table('residencia')
            ->where('id_unidad', (int) $d['id_unidad'])
            ->where('id_usuario', (int) $d['id_usuario'])
            ->whereNull('hasta')
            ->update(['hasta' => $d['desde']]);

        // Crear nueva
        DB::table('residencia')->insert([
            'id_unidad'   => (int) $d['id_unidad'],
            'id_usuario'  => (int) $d['id_usuario'],
            'origen'      => $d['origen'],
            'desde'       => $d['desde'],
            'observacion' => $d['observacion'] ?? null,
        ]);

        return back()->with('ok', 'Residencia guardada.');
    }

    /* ======================================================================
     * TERMINAR
     * ====================================================================== */
    public function terminar($id, Request $r)
    {
        $u       = auth()->user();
        $role    = $u->rol ?? ($u->tipo_usuario ?? $u->role ?? null);
        $isSuper = method_exists($u,'hasRole') ? $u->hasRole('super_admin') : ($role === 'super_admin');
        $allowed = $this->allowedCondoIds();
        $schema  = DB::getSchemaBuilder();
        $uCondoCol = $this->unidadCondoColumn();
        $hasGrupo  = $schema->hasTable('grupo') && in_array('id_condominio', array_map('strtolower', $schema->getColumnListing('grupo')), true);

        $fecha = $r->validate(['hasta' => ['required', 'date']])['hasta'];

        if (!$isSuper) {
            if (empty($allowed)) {
                return back()->with('err', 'No tienes condominios asignados.');
            }

            $okQ = DB::table('residencia as r')
                ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                ->where('r.id_residencia', (int) $id);

            if ($uCondoCol) {
                $okQ->whereIn("u.$uCondoCol", $allowed);
            } elseif ($hasGrupo) {
                $okQ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                    ->whereIn('g.id_condominio', $allowed);
            } else {
                return back()->with('err', 'No estás autorizado para finalizar esa residencia.');
            }

            if (!$okQ->exists()) {
                return back()->with('err', 'No estás autorizado para finalizar esa residencia.');
            }
        }

        DB::table('residencia')->where('id_residencia', (int) $id)->update(['hasta' => $fecha]);
        return back()->with('ok', 'Residencia finalizada.');
    }
}
