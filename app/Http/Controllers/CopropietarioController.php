<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopropietarioController extends Controller
{
    /**
     * Ids de condominios permitidos según el usuario autenticado.
     * - super_admin: todos
     * - admin: pivote admin_user_condo (si existe) + ctx_condo_id
     */
    private function allowedCondoIds(): array
    {
        $u    = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);

        if ($role === 'super_admin') {
            return DB::table('condominio')
                ->pluck('id_condominio')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $ids = [];

        if (DB::getSchemaBuilder()->hasTable('admin_user_condo')) {
            $ids = DB::table('admin_user_condo')
                ->where('id_usuario', $u->id_usuario ?? $u->id)
                ->pluck('id_condominio')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $ctx = session('ctx_condo_id');
        if ($ctx) {
            $ids[] = (int) $ctx;
        }

        return array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));
    }

    /**
     * Listado + combos.
     * - Unidades: sólo de condominios permitidos
     * - Usuarios elegibles: tipo 'copropietario' sin vigencia activa en esos condominios
     * - Últimas vigencias (limit 80) filtradas por condominios permitidos
     */
    public function index()
    {
        $u      = auth()->user();
        $role   = $u->rol ?? ($u->tipo_usuario ?? null);
        $isSA   = $role === 'super_admin';
        $allowed = $this->allowedCondoIds();

        // ===== Unidades visibles
        $unidades = DB::table('unidad as u')
            ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
            ->when(!$isSA, function ($q) use ($allowed) {
                if (empty($allowed)) {
                    $q->whereRaw('1=0');
                } else {
                    $q->whereIn('g.id_condominio', $allowed);
                }
            })
            ->orderBy('u.id_unidad')
            ->limit(300)
            ->get([
                'u.id_unidad',
                'u.codigo',
            ]);

        // ===== Copropietarios ELEGIBLES (sin vigencia activa en esos condominios)
        // 1) Usuarios tipo copropietario
        $usuariosBase = DB::table('usuario as usu')
            ->where('usu.tipo_usuario', 'copropietario');

        // 2) Usuarios con alguna vigencia vigente en los condominios permitidos
        $usuariosConVigencia = DB::table('copropietario as c')
            ->join('unidad as u', 'u.id_unidad', '=', 'c.id_unidad')
            ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
            ->whereNull('c.hasta')
            ->when(!$isSA, function ($q) use ($allowed) {
                if (empty($allowed)) {
                    $q->whereRaw('1=0');
                } else {
                    $q->whereIn('g.id_condominio', $allowed);
                }
            })
            ->pluck('c.id_usuario')
            ->unique()
            ->all();

        if ($isSA) {
            // SA: elegibles = todos los copropietarios que NO están en $usuariosConVigencia
            $usuariosElegibles = $usuariosBase
                ->when(!empty($usuariosConVigencia), function ($q) use ($usuariosConVigencia) {
                    $q->whereNotIn('usu.id_usuario', $usuariosConVigencia);
                })
                ->orderBy('usu.nombres')
                ->limit(200)
                ->get();
        } else {
            if (empty($allowed)) {
                $usuariosElegibles = collect();
            } else {
                $usuariosElegibles = $usuariosBase
                    ->when(!empty($usuariosConVigencia), function ($q) use ($usuariosConVigencia) {
                        $q->whereNotIn('usu.id_usuario', $usuariosConVigencia);
                    })
                    ->orderBy('usu.nombres')
                    ->limit(200)
                    ->get();
            }
        }

        // ===== Últimas vigencias (filtradas por condominios permitidos)
        $ult = DB::table('copropietario as c')
            ->join('unidad as u', 'u.id_unidad', '=', 'c.id_unidad')
            ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
            ->join('usuario as usu', 'usu.id_usuario', '=', 'c.id_usuario')
            ->when(!$isSA, function ($q) use ($allowed) {
                if (empty($allowed)) {
                    $q->whereRaw('1=0');
                } else {
                    $q->whereIn('g.id_condominio', $allowed);
                }
            })
            ->select(
                'c.*',
                'u.codigo as unidad',
                'usu.email',
                'usu.nombres',
                'usu.apellidos'
            )
            ->orderByDesc('c.id_coprop')
            ->limit(80)
            ->get();

        return view('coprop_index', [
            'ult'               => $ult,
            'unidades'          => $unidades,
            'usuariosElegibles' => $usuariosElegibles,
        ]);
    }

    /**
     * Crear relación copropietaria.
     * Valida:
     *  - Unidad pertenece a condominios permitidos (si no es SA)
     *  - Suma de porcentajes vigentes en la unidad <= 100
     */
    public function store(Request $r)
    {
        $u      = auth()->user();
        $role   = $u->rol ?? ($u->tipo_usuario ?? null);
        $isSA   = $role === 'super_admin';
        $allowed = $this->allowedCondoIds();

        $d = $r->validate([
            'id_unidad'   => ['required', 'integer'],
            'id_usuario'  => ['required', 'integer'],
            'porcentaje'  => ['required', 'numeric', 'min:0', 'max:100'],
            'desde'       => ['required', 'date'],
        ]);

        // Seguridad: validar unidad del condominio permitido
        if (!$isSA) {
            if (empty($allowed)) {
                return back()->with('error', 'No tienes condominios asignados.')->withInput();
            }

            $okUnidad = DB::table('unidad as u')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                ->where('u.id_unidad', (int) $d['id_unidad'])
                ->whereIn('g.id_condominio', $allowed)
                ->exists();

            if (!$okUnidad) {
                return back()->with('error', 'No estás autorizado para operar en esa unidad.')->withInput();
            }
        }

        // Suma de porcentajes vigentes
        $sumAct = (float) DB::table('copropietario')
            ->where('id_unidad', (int) $d['id_unidad'])
            ->whereNull('hasta')
            ->sum('porcentaje');

        if ($sumAct + (float) $d['porcentaje'] > 100.0001) {
            return back()->with('error', 'El total de porcentajes vigentes supera 100%.')->withInput();
        }

        DB::table('copropietario')->insert([
            'id_unidad'  => (int) $d['id_unidad'],
            'id_usuario' => (int) $d['id_usuario'],
            'porcentaje' => (float) $d['porcentaje'],
            'desde'      => $d['desde'],
            'hasta'      => null,
        ]);

        return back()->with('ok', 'Copropietario agregado.');
    }

    /**
     * Terminar relación copropietaria (verifica permiso por condominio).
     */
    public function terminar($id, Request $r)
    {
        $u      = auth()->user();
        $role   = $u->rol ?? ($u->tipo_usuario ?? null);
        $isSA   = $role === 'super_admin';
        $allowed = $this->allowedCondoIds();

        $fecha = $r->validate(['hasta' => ['required', 'date']])['hasta'];

        if (!$isSA) {
            if (empty($allowed)) {
                return back()->with('error', 'No tienes condominios asignados.');
            }

            $ok = DB::table('copropietario as c')
                ->join('unidad as u', 'u.id_unidad', '=', 'c.id_unidad')
                ->leftJoin('grupo as g', 'g.id_grupo', '=', 'u.id_grupo')
                ->where('c.id_coprop', (int) $id)
                ->whereIn('g.id_condominio', $allowed)
                ->exists();

            if (!$ok) {
                return back()->with('error', 'No estás autorizado para finalizar esa relación.');
            }
        }

        DB::table('copropietario')
            ->where('id_coprop', (int) $id)
            ->update(['hasta' => $fecha]);

        return back()->with('ok', 'Relación copropietaria finalizada.');
    }
}
