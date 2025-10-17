<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUserCondoController extends Controller
{
    /**
     * Normaliza la lista de administradores desde 'usuario' y/o 'users'
     * en forma: [{id: int, label: string}]
     */
    private function getAdminsNormalized()
    {
        $admins = collect();

        /* -------- Fuente 1: tabla 'usuario' -------- */
        if (Schema::hasTable('usuario')) {
            $fromUsuario = DB::table('usuario')
                ->whereIn('tipo_usuario', ['admin', 'admin_condo', 'super_admin'])
                ->selectRaw('id_usuario as id, CONCAT(COALESCE(nombres,""), " ", COALESCE(apellidos,""), " (", COALESCE(email,""), ")") as label')
                ->get();
            $admins = $admins->merge($fromUsuario);
        }

        /* -------- Fuente 2: tabla 'users' (con chequeo de columnas) -------- */
        if (Schema::hasTable('users')) {
            $q = DB::table('users')
                ->selectRaw('id as id, CONCAT(COALESCE(name,""), " (", COALESCE(email,""), ")") as label');

            // Filtrar por el campo que realmente exista
            if (Schema::hasColumn('users', 'rol')) {
                $q->whereIn('rol', ['admin', 'admin_condo', 'super_admin']);
            } elseif (Schema::hasColumn('users', 'tipo_usuario')) {
                $q->whereIn('tipo_usuario', ['admin', 'admin_condo', 'super_admin']);
            } elseif (Schema::hasColumn('users', 'is_admin')) {
                // Fallback común en algunos proyectos
                $q->where('is_admin', 1);
            } else {
                // Si no hay ninguna pista de rol en 'users', mejor NO traemos nada desde aquí
                $q->whereRaw('1=0');
            }

            $fromUsers = $q->get();
            $admins = $admins->merge($fromUsers);
        }

        return $admins
            ->unique('id')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function index(Request $r)
    {
        $admins = $this->getAdminsNormalized();

        if ($admins->isEmpty()) {
            return view('admin_uac', [
                'admins'      => collect(),
                'selAdmin'    => null,
                'condos'      => collect(),
                'assignedIds' => [],
                'count'       => 0,
            ])->with('err', 'No se encontraron administradores (revisa columnas rol/tipo_usuario/is_admin en users o tipo_usuario en usuario).');
        }

        $selAdmin = (int)($r->query('id_usuario') ?? $admins->first()->id);

        $condos = DB::table('condominio')
            ->select('id_condominio','nombre')
            ->orderBy('nombre')
            ->get();

        $assignedIds = [];
        if (Schema::hasTable('admin_user_condo')) {
            $assignedIds = DB::table('admin_user_condo')
                ->where('id_usuario', $selAdmin)
                ->pluck('id_condominio')
                ->map(fn($v)=>(int)$v)
                ->all();
        }

        return view('admin_uac', [
            'admins'      => $admins,
            'selAdmin'    => $selAdmin,
            'condos'      => $condos,
            'assignedIds' => $assignedIds,
            'count'       => count($assignedIds),
        ]);
    }

    public function save(Request $r)
    {
        $d = $r->validate([
            'id_usuario'      => ['required','integer'],
            'id_condominio'   => ['array'],
            'id_condominio.*' => ['integer'],
        ]);

        if (!Schema::hasTable('admin_user_condo')) {
            Schema::create('admin_user_condo', function ($table) {
                $table->unsignedBigInteger('id_usuario');
                $table->unsignedBigInteger('id_condominio');
                $table->primary(['id_usuario','id_condominio']);
                $table->index('id_usuario');
                $table->index('id_condominio');
            });
        }

        $uid = (int)$d['id_usuario'];
        $ids = collect($d['id_condominio'] ?? [])->filter()->map(fn($v)=>(int)$v)->unique()->values()->all();

        DB::transaction(function () use ($uid,$ids) {
            DB::table('admin_user_condo')->where('id_usuario',$uid)->delete();
            if (!empty($ids)) {
                $rows = array_map(fn($cid)=>['id_usuario'=>$uid,'id_condominio'=>$cid], $ids);
                DB::table('admin_user_condo')->insert($rows);
            }
        });

        return back()->with('ok','Asignación guardada.');
    }
}
