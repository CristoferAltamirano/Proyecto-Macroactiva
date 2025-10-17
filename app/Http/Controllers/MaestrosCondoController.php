<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditoriaService;

class MaestrosCondoController extends Controller
{
    /* ====================== Helpers de roles / alcance ====================== */

    private function userHasRole($user, string $role): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }
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

    /**
     * IDs de condominios permitidos para el usuario.
     *  - super_admin => null (sin restricción)
     *  - relación en User (varios nombres comunes)
     *  - columnas en users
     *  - pivots comunes
     *  - contexto de sesión
     *
     * Retorna:
     *   null  => sin restricción
     *   []    => ninguno (no ver/editar nada)
     *   [ids] => lista permitida
     */
    private function allowedCondoIds($user): ?array
    {
        if ($this->userIsSuper($user)) {
            return null;
        }

        $ids = [];

        // Contexto de sesión (si existiera)
        $ctx = session('ctx.id_condominio')
            ?? session('ctx_id_condominio')
            ?? session('ctx_condo_id')
            ?? session('id_condominio')
            ?? null;
        if ($ctx) $ids[] = (int) $ctx;

        // Relaciones típicas
        foreach (['condominios','condominio','condos','adminCondominios','condominiosAsignados','condominiosPermitidos','condominios_admin'] as $rel) {
            if (method_exists($user, $rel)) {
                try {
                    $pl = $user->{$rel}()->pluck('id_condominio');
                    if ($pl->isEmpty()) $pl = $user->{$rel}()->pluck('condominio_id');
                    $ids = array_merge($ids, $pl->all());
                } catch (\Throwable $e) {}
            }
        }

        // Columnas en users
        foreach (['id_condominio','condominio_id','id_condo','cod_condominio'] as $col) {
            if (isset($user->{$col}) && $user->{$col} !== null) {
                $ids[] = $user->{$col};
                break;
            }
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
            try {
                $ids = array_merge($ids, DB::table($table)->where($uCol,$userId)->pluck($cCol)->all());
            } catch (\Throwable $e) {}
        }

        return $this->normalizeIds($ids);
    }

    /* ============================== Panel ============================== */

    public function panel()
    {
        $user    = auth()->user();
        $allowed = $this->allowedCondoIds($user); // null => sin restricción

        // Lista de condominios para los selects
        if ($this->userIsSuper($user)) {
            $condos = DB::table('condominio')
                ->select('id_condominio','nombre')
                ->orderBy('nombre')->get();
        } else {
            if (is_array($allowed) && count($allowed) > 0) {
                $condos = DB::table('condominio')
                    ->select('id_condominio','nombre')
                    ->whereIn('id_condominio', $allowed)
                    ->orderBy('nombre')->get();
            } else {
                $condos = collect(); // ninguno asignado
            }
        }

        // Reglas de interés (limit 50), siempre mostrando nombre de condominio y segmento
        $reglasQ = DB::table('interes_regla as r')
            ->join('condominio as c','c.id_condominio','=','r.id_condominio')
            ->join('cat_segmento as s','s.id_segmento','=','r.id_segmento')
            ->select('r.*','c.nombre as condominio','s.nombre as segmento')
            ->orderByDesc('r.vigente_desde')
            ->limit(50);

        if (is_array($allowed)) {
            if (count($allowed) > 0) {
                $reglasQ->whereIn('r.id_condominio', $allowed);
            } else {
                $reglasQ->whereRaw('1=0');
            }
        }
        $reglas = $reglasQ->get();

        // Segmentos: si la tabla tiene id_condominio limitamos; si no, se asumen globales
        $segQ = DB::table('cat_segmento');
        if (Schema::hasColumn('cat_segmento', 'id_condominio') && is_array($allowed)) {
            if (count($allowed) > 0) {
                $segQ->whereIn('id_condominio', $allowed);
            } else {
                $segQ->whereRaw('1=0');
            }
        }
        $seg = $segQ->get();

        // Parámetros de reglamento (por condominio)
        $paramsQ = DB::table('param_reglamento as p')
            ->join('condominio as c','c.id_condominio','=','p.id_condominio')
            ->select('p.*','c.nombre');

        if (is_array($allowed)) {
            if (count($allowed) > 0) {
                $paramsQ->whereIn('p.id_condominio', $allowed);
            } else {
                $paramsQ->whereRaw('1=0');
            }
        }
        $params = $paramsQ->get();

        return view('maestros_condo', compact('condos','reglas','seg','params'));
    }

    /* ============================== Guardar parámetros ============================== */

    public function saveParams(Request $r)
    {
        $d = $r->validate([
            'id_condominio'              => ['required','integer'],
            'recargo_fondo_reserva_pct'  => ['required','numeric','min:0'],
            'interes_mora_anual_pct'     => ['nullable','numeric','min:0'],
            'dias_gracia'                => ['required','integer','min:0'],
            'multa_morosidad_fija'       => ['nullable','numeric','min:0'],
        ]);

        // Autorización por alcance
        $allowed = $this->allowedCondoIds($r->user());
        if (is_array($allowed) && !in_array((int)$d['id_condominio'], $allowed, true)) {
            return back()->withErrors(['id_condominio' => 'No tienes permiso para gestionar ese condominio.'])->withInput();
        }

        DB::table('param_reglamento')->updateOrInsert(['id_condominio' => (int)$d['id_condominio']], $d);

        AuditoriaService::log('param_reglamento', (int)$d['id_condominio'], 'GUARDAR', $d);

        return back()->with('ok','Parámetros guardados.');
    }

    /* ============================== Guardar regla de interés ============================== */

    public function saveInteres(Request $r)
    {
        $d = $r->validate([
            'id_condominio'   => ['required','integer'],
            'id_segmento'     => ['required','integer'],
            'vigente_desde'   => ['required','date'],
            'vigente_hasta'   => ['nullable','date'],
            'tasa_anual_pct'  => ['required','numeric','min:0'],
            'dias_gracia'     => ['required','integer','min:0'],
            'fuente_url'      => ['nullable','url'],
            'comentario'      => ['nullable','string','max:300'],
        ]);

        // Autorización por alcance
        $allowed = $this->allowedCondoIds($r->user());
        if (is_array($allowed) && !in_array((int)$d['id_condominio'], $allowed, true)) {
            return back()->withErrors(['id_condominio' => 'No tienes permiso para gestionar ese condominio.'])->withInput();
        }

        $id = DB::table('interes_regla')->insertGetId($d);

        AuditoriaService::log('interes_regla', (int)$id, 'CREAR', $d);

        return back()->with('ok','Regla de interés creada.');
    }
}
