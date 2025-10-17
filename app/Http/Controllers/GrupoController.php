<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditoriaService;

class GrupoController extends Controller
{
    /**
     * IDs de condominios permitidos para el usuario actual.
     * - super_admin: todos.
     * - admin:
     *      * si existe pivote admin_user_condo => usa esos IDs
     *      * agrega session('ctx_condo_id') si existe
     *      * ⚠️ si queda vacío: PERMITIR TODOS (fallback “admin con todos los condominios”)
     */
    private function allowedCondoIds(): array
    {
        $u = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);

        // super_admin: sin restricción
        if ($role === 'super_admin') {
            return DB::table('condominio')->pluck('id_condominio')->map(fn($v) => (int) $v)->all();
        }

        // admin: construir lista permitida
        $ids = [];

        // 1) Si existe pivote admin_user_condo, la usamos
        if (Schema::hasTable('admin_user_condo')) {
            $ids = DB::table('admin_user_condo')
                ->where('id_usuario', $u->id_usuario ?? $u->id)
                ->pluck('id_condominio')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        // 2) Respaldo: ctx_condo_id de sesión
        $ctx = session('ctx_condo_id');
        if ($ctx) {
            $ids[] = (int) $ctx;
        }

        // 3) Normalizamos
        $ids = array_values(array_unique(array_filter($ids, fn($v) => (int)$v > 0)));

        // 4) ⚠️ Fallback importante:
        //    Si no hay nada asignado explícitamente, considera que este admin
        //    tiene acceso a TODOS los condominios (tu caso de “autorizado a todos”).
        if (empty($ids)) {
            return DB::table('condominio')->pluck('id_condominio')->map(fn($v) => (int) $v)->all();
        }

        return $ids;
    }

    public function index(Request $r)
    {
        $u = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);
        $isSuper = $role === 'super_admin';

        $allowedIds = $this->allowedCondoIds();

        // Listado de condominios para el combo o para mostrar "activo"
        if ($isSuper) {
            $condos = DB::table('condominio')->orderBy('nombre')->get();
        } else {
            $condos = empty($allowedIds)
                ? collect()
                : DB::table('condominio')->whereIn('id_condominio', $allowedIds)->orderBy('nombre')->get();
        }

        // id del condominio seleccionado (siempre dentro de lo permitido)
        $queryId = $r->query('id_condominio');
        $idCondo = null;

        if ($isSuper) {
            $idCondo = $queryId ? (int) $queryId : ($condos->first()->id_condominio ?? null);
        } else {
            if ($queryId && in_array((int)$queryId, $allowedIds, true)) {
                $idCondo = (int) $queryId;
            } else {
                $idCondo = $condos->first()->id_condominio ?? null;
            }
        }

        // Cargar grupos
        $grupos = $idCondo
            ? DB::table('grupo')->where('id_condominio', $idCondo)->orderBy('nombre')->get()
            : collect();

        return view('grupos_index', compact('condos', 'grupos', 'idCondo'));
    }

    public function store(Request $r)
    {
        $u = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);
        $isSuper = $role === 'super_admin';

        $d = $r->validate([
            'id_condominio' => ['required', 'integer'],
            'nombre'        => ['required', 'string', 'max:80'],
            'tipo'          => ['required', 'string', 'max:45'],
        ]);

        // Seguridad: validar que el admin tenga permiso sobre ese condominio
        if (!$isSuper) {
            $allowedIds = $this->allowedCondoIds();
            if (!in_array((int)$d['id_condominio'], $allowedIds, true)) {
                return back()->with('err', 'No estás autorizado para operar en ese condominio.')
                             ->withInput();
            }
        }

        DB::table('grupo')->updateOrInsert(
            ['id_condominio' => (int) $d['id_condominio'], 'nombre' => $d['nombre']],
            [
                'id_condominio' => (int) $d['id_condominio'],
                'nombre'        => $d['nombre'],
                'tipo'          => $d['tipo'],
            ]
        );

        $id = (int) DB::table('grupo')
            ->where('id_condominio', (int) $d['id_condominio'])
            ->where('nombre', $d['nombre'])
            ->value('id_grupo');

        AuditoriaService::log('grupo', $id, 'GUARDAR', $d);

        return back()->with('ok', 'Grupo guardado.');
    }
}
