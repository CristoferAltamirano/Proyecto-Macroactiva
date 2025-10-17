<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression as Expr;
use Illuminate\Validation\Rule;

class UnidadController extends Controller
{
    /** ======== Helpers de permisos por condominio ======== */

    /** Devuelve el rol del usuario autenticado. */
    private function userRole(): ?string
    {
        $u = auth()->user();
        if (!$u) return null;
        // soporta ambos nombres de campo
        return $u->rol ?? ($u->tipo_usuario ?? null);
    }

    /**
     * Devuelve lista de IDs de condominio permitidos.
     * - super_admin: null => sin restricción
     * - admin: usa admin_user_condo si existe; si no, session('ctx_condo_id')
     */
    private function allowedCondoIdsForAdmin(): ?array
    {
        $role = $this->userRole();

        if ($role === 'super_admin') {
            return null; // sin restricción
        }

        if ($role === 'admin') {
            // 1) Si existe tabla de asignación, úsala
            if (Schema::hasTable('admin_user_condo')) {
                $ids = DB::table('admin_user_condo')
                    ->where('id_usuario', auth()->id())
                    ->pluck('id_condominio')
                    ->filter()
                    ->map(fn($v) => (int)$v)
                    ->values()
                    ->all();

                if (!empty($ids)) return $ids;
            }

            // 2) Fallback: contexto de sesión
            $cid = (int) (session('ctx_condo_id') ?? 0);
            return $cid > 0 ? [$cid] : [];
        }

        // Otros roles (copropietario/residente) no deberían entrar aquí.
        return [];
    }

    /** Normaliza/asegura id_condominio válido según permisos. */
    private function coerceCondoId(?int $idCondo): ?int
    {
        $allowed = $this->allowedCondoIdsForAdmin();

        // super_admin -> sin restricción, se acepta lo recibido
        if ($allowed === null) {
            return $idCondo;
        }

        // admin con lista vacía -> intenta usar ctx; si no hay, nulo
        if (empty($allowed)) {
            $ctx = (int) (session('ctx_condo_id') ?? 0);
            return $ctx > 0 ? $ctx : null;
        }

        // si viene uno por query y está permitido, úsalo; si no, toma el primero permitido
        if ($idCondo && in_array((int)$idCondo, $allowed, true)) {
            return (int)$idCondo;
        }

        return $allowed[0] ?? null;
    }

    /** Helper: devuelve u.col como alias, si existe. */
    private function pick(string $table, array $candidates, string $alias): Expr
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) {
                return DB::raw("u.$c as $alias");
            }
        }
        return DB::raw("NULL as $alias");
    }

    /** Panel: formulario + listado (primeros 200), con filtro por condominio opcional */
    public function index(Request $r)
    {
        // id_condominio que viene por query (o null)
        $idCondoReq = $r->integer('id_condominio') ?: null;

        // Forzamos id_condominio válido según permisos
        $idCondo = $this->coerceCondoId($idCondoReq);

        // === Combos de Condominios visibles ===
        // super_admin: todos; admin: solo permitidos
        $allowed = $this->allowedCondoIdsForAdmin();

        $condosQuery = DB::table('condominio')->select('id_condominio','nombre')->orderBy('nombre');
        if ($allowed !== null) {
            // restringir si es admin
            $condosQuery->whereIn('id_condominio', $allowed ?: [-1]); // -1 fuerza lista vacía si no hay
        }
        $condos = $condosQuery->get();

        // === Grupos filtrados por condominio (si se eligió uno) y permisos ===
        $grupos = DB::table('grupo')
            ->when($idCondo, fn($q) => $q->where('id_condominio', $idCondo))
            ->when(
                $allowed !== null && empty($idCondo),
                fn($q) => $q->whereIn('id_condominio', $allowed ?: [-1])
            )
            ->orderBy('nombre')
            ->get();

        // === Unidades (con joins y permisos) ===
        $select = [
            DB::raw('u.id_unidad as id_unidad'),
            DB::raw('u.id_grupo as id_grupo'),
            DB::raw('u.codigo as codigo'),
            $this->pick('unidad', ['direccion'], 'direccion'),

            // Tipo grupo viene de la tabla grupo.tipo
            DB::raw('g.tipo as tipo_grupo'),

            // Nombres de catálogos (si existen); si no, mostramos el ID
            Schema::hasTable('cat_unidad_tipo')
                ? DB::raw('cut.nombre as tipo_unidad')
                : DB::raw('u.id_unidad_tipo as tipo_unidad'),

            Schema::hasTable('cat_segmento')
                ? DB::raw('cs.nombre as segmento')
                : DB::raw('u.id_segmento as segmento'),

            // Estado derivado de habitable
            DB::raw("CASE WHEN u.habitable = 1 THEN 'activo' ELSE 'inactivo' END as estado"),

            $this->pick('unidad', ['rol_sii','rol'], 'rol_sii'),
            $this->pick('unidad', ['metros2','m2','superficie'], 'm2'),
            $this->pick('unidad', ['coef_prop','coef_propiedad','coef'], 'coef_propiedad'),
            $this->pick('unidad', ['anexo_incluido','anexo_incl'], 'anexo_incluido'),
            $this->pick('unidad', ['anexo_cobrable','anexo_cob'], 'anexo_cobrable'),
            $this->pick('unidad', ['habitable','es_habitable'], 'habitable'),
            DB::raw('g.nombre as grupo'),
        ];

        $q = DB::table('unidad as u')
            ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo');

        if (Schema::hasTable('cat_unidad_tipo')) {
            $q->leftJoin('cat_unidad_tipo as cut','cut.id_unidad_tipo','=','u.id_unidad_tipo');
        }
        if (Schema::hasTable('cat_segmento')) {
            $q->leftJoin('cat_segmento as cs','cs.id_segmento','=','u.id_segmento');
        }

        $unidades = $q
            // filtro por id_condominio elegido
            ->when($idCondo, fn($qq) => $qq->where('g.id_condominio', $idCondo))
            // si no hay idCondo, pero es admin, restringe por sus permitidos
            ->when(
                $allowed !== null && empty($idCondo),
                fn($qq) => $qq->whereIn('g.id_condominio', $allowed ?: [-1])
            )
            ->select($select)
            ->orderBy('u.id_unidad')
            ->limit(200)
            ->get();

        /* ======= CATÁLOGOS PARA EL FORM ======= */
        // Tipos de unidad
        if (Schema::hasTable('cat_unidad_tipo')) {
            $tipos = DB::table('cat_unidad_tipo')->select('id_unidad_tipo','nombre')->orderBy('nombre')->get();
        } else {
            $tipos = collect([
                (object)['id_unidad_tipo'=>1, 'nombre'=>'Vivienda'],
                (object)['id_unidad_tipo'=>2, 'nombre'=>'Departamento'],
                (object)['id_unidad_tipo'=>3, 'nombre'=>'Local'],
                (object)['id_unidad_tipo'=>4, 'nombre'=>'Bodega'],
                (object)['id_unidad_tipo'=>5, 'nombre'=>'Estacionamiento'],
            ]);
        }

        // Subtipos legacy (la vista ya no los usa, pero se devuelven por compatibilidad)
        if (Schema::hasTable('cat_viv_subtipo')) {
            $subs = DB::table('cat_viv_subtipo')->select('id_viv_subtipo','nombre')->orderBy('nombre')->get();
        } else {
            $subs = collect([
                (object)['id_viv_subtipo'=>1, 'nombre'=>'(n/a)'],
                (object)['id_viv_subtipo'=>2, 'nombre'=>'Casa'],
                (object)['id_viv_subtipo'=>3, 'nombre'=>'Depto'],
                (object)['id_viv_subtipo'=>4, 'nombre'=>'Oficina'],
            ]);
        }

        // Segmentos
        if (Schema::hasTable('cat_segmento')) {
            $segmentos = DB::table('cat_segmento')->select('id_segmento','nombre')->orderBy('nombre')->get();
        } else {
            $segmentos = collect([
                (object)['id_segmento'=>1,'nombre'=>'Residencial'],
                (object)['id_segmento'=>2,'nombre'=>'Comercial'],
                (object)['id_segmento'=>3,'nombre'=>'Otro'],
            ]);
        }

        return view('unidades_index', compact(
            'condos','idCondo','grupos','unidades','tipos','subs','segmentos'
        ));
    }

    /** Crear/Actualizar unidad (el form puede traer id_unidad oculto) */
    public function store(Request $r)
    {
        $editing = $r->filled('id_unidad');

        /* ===== Seguridad: el grupo debe pertenecer a un condominio permitido ===== */
        $idGrupo = (int) $r->input('id_grupo');
        if ($idGrupo <= 0) {
            return back()->with('err', 'Debe seleccionar un grupo válido.')->withInput();
        }

        // id_condominio del grupo
        $grupoCondo = DB::table('grupo')->where('id_grupo', $idGrupo)->value('id_condominio');

        if (!$grupoCondo) {
            return back()->with('err', 'El grupo seleccionado no existe.')->withInput();
        }

        $allowed = $this->allowedCondoIdsForAdmin();
        if ($allowed !== null) {
            // admin: debe estar en su lista
            if (empty($allowed) || !in_array((int)$grupoCondo, $allowed, true)) {
                abort(403, 'No tienes permiso para operar en este condominio.');
            }
        }
        /* ===== Fin seguridad ===== */

        /* ===== Normalizar coef_propiedad (acepta 6,5 / 6.5 / 6.5%) ===== */
        $coefInput = $r->input('coef_propiedad');
        if ($coefInput !== null && $coefInput !== '') {
            $norm = str_replace('%','',str_replace(',','.',$coefInput));
            if (is_numeric($norm)) {
                $val = (float)$norm;
                if ($val > 1 && $val <= 100) $val = $val / 100.0;
                $r->merge(['coef_propiedad' => $val]);
            }
        }

        // Validaciones (unicidad por grupo + código)
        $messages = [
            'codigo.unique'            => 'El código ya existe en este grupo.',
            'coef_propiedad.lte'       => 'El coeficiente debe ser ≤ 1 (ej: 0.065).',
            'coef_propiedad.gte'       => 'El coeficiente debe ser ≥ 0.',
            'coef_propiedad.numeric'   => 'El coeficiente debe ser numérico. Ej: 0.065 (6.5%).',
        ];

        $rules = [
            'id_unidad'       => ['nullable','integer','min:1'],
            'id_grupo'        => ['required','integer','min:1'],

            'codigo'          => [
                'required','string','max:40',
                Rule::unique('unidad','codigo')
                    ->where(fn($q)=>$q->where('id_grupo', $idGrupo))
                    ->when($editing, fn($rule)=>$rule->ignore((int)$r->input('id_unidad'),'id_unidad'))
            ],

            // Vista envía texto; lo convertimos a IDs más abajo
            'tipo_unidad'     => ['nullable','string','max:60'],
            'segmento'        => ['nullable','string','max:60'],
            'tipo_grupo'      => ['nullable','in:Torre,Etapa,Loteo'], // se aplicará sobre tabla grupo

            'estado'          => ['nullable','in:activo,inactivo'], // se mapea a habitable
            'direccion'       => ['nullable','string','max:200'],
            'rol_sii'         => ['nullable','string','max:40'],
            'm2'              => ['nullable','numeric','min:0','max:1000000'],
            'coef_propiedad'  => ['nullable','numeric','gte:0','lte:1'],

            'anexo_incluido'  => ['nullable'],
            'anexo_cobrable'  => ['nullable'],
            'habitable'       => ['nullable'],
        ];

        $d = $r->validate($rules, $messages);

        $T = 'unidad';
        $data = [
            'id_grupo' => $idGrupo,
            'codigo'   => $r->input('codigo'),
        ];

        // IDs desde catálogos (si existen). La vista envía texto; aceptamos también números.
        // id_unidad_tipo
        $idUnidadTipo = null;
        $tipoUnidadIn = $r->input('tipo_unidad');
        if ($tipoUnidadIn !== null && $tipoUnidadIn !== '') {
            if (is_numeric($tipoUnidadIn)) {
                $idUnidadTipo = (int)$tipoUnidadIn;
            } elseif (Schema::hasTable('cat_unidad_tipo')) {
                $idUnidadTipo = DB::table('cat_unidad_tipo')
                    ->where('nombre', $tipoUnidadIn)
                    ->value('id_unidad_tipo');
            }
        }
        if ($idUnidadTipo !== null && Schema::hasColumn($T,'id_unidad_tipo')) {
            $data['id_unidad_tipo'] = $idUnidadTipo;
        }

        // id_segmento
        $idSegmento = null;
        $segmentoIn = $r->input('segmento');
        if ($segmentoIn !== null && $segmentoIn !== '') {
            if (is_numeric($segmentoIn)) {
                $idSegmento = (int)$segmentoIn;
            } elseif (Schema::hasTable('cat_segmento')) {
                $idSegmento = DB::table('cat_segmento')
                    ->where('nombre', $segmentoIn)
                    ->value('id_segmento');
            }
        }
        if ($idSegmento !== null && Schema::hasColumn($T,'id_segmento')) {
            $data['id_segmento'] = $idSegmento;
        }

        // Dirección / Rol
        if (Schema::hasColumn($T,'direccion')) $data['direccion'] = $r->input('direccion');
        if (Schema::hasColumn($T,'rol_sii'))   $data['rol_sii']   = $r->input('rol_sii');

        // m2 -> metros2
        if (Schema::hasColumn($T,'metros2'))   $data['metros2']   = $r->input('m2');

        // coeficiente
        if (Schema::hasColumn($T,'coef_prop')) $data['coef_prop'] = $r->input('coef_propiedad');

        // Estado -> habitable
        if ($r->has('estado') && Schema::hasColumn($T,'habitable')) {
            $data['habitable'] = $r->input('estado') === 'activo' ? 1 : 0;
        }

        // Checkboxes
        if (Schema::hasColumn($T,'anexo_incluido')) $data['anexo_incluido'] = $r->boolean('anexo_incluido') ? 1 : 0;
        if (Schema::hasColumn($T,'anexo_cobrable')) $data['anexo_cobrable'] = $r->boolean('anexo_cobrable') ? 1 : 0;

        // (Opcional) actualizar tipo del grupo seleccionado
        if ($r->filled('tipo_grupo')) {
            DB::table('grupo')->where('id_grupo', $idGrupo)
                ->update(['tipo' => $r->input('tipo_grupo')]);
        }

        if ($editing) {
            DB::table($T)->where('id_unidad', (int)$r->id_unidad)->update($data);
            return back()->with('ok','Unidad actualizada.');
        } else {
            $id = DB::table($T)->insertGetId($data);
            return back()->with('ok','Unidad creada (ID '.$id.').');
        }
    }
}
