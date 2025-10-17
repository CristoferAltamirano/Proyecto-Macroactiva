<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private const TBL_USERS   = 'usuario';

    /** Candidatos por tema (exact match tiene prioridad) */
    private const COL_CANDS = [
        'pk'       => ['id_usuario','id','user_id'],
        'email'    => ['email','correo','mail'],
        'tipo'     => ['tipo_usuario','rol','role','tipo'],

        // RUT / RUN / Documento
        'rut'      => ['rut','run','documento','dni','rut_usuario','run_usuario','rut_base','rut_completo','num_documento'],
        'dv'       => ['dv','dvr','dv_rut','digito','digito_verificador','dvrun','dv_run','rut_dv'],

        'activo'   => ['activo','estado','is_active','enabled','status'],
        'nombres'  => ['nombres','nombre','name','first_name'],
        'apellidos'=> ['apellidos','apellido','last_name','lastname'],
        'telefono' => ['telefono','fono','phone','tel','telefono_contacto','celular'],
        'direccion'=> ['direccion','address','domicilio'],

        // <<< AQUI incluimos pass_hash y variantes
        'password' => ['password','pass_hash','password_hash','clave','clave_hash','contrasena','contrasenia','hash'],

        // timestamps
        'created'  => ['created_at','creado_at','fecha_creacion'],
        'updated'  => ['updated_at','fecha_actualizacion'],
    ];

    /** Busca por nombre exacto dentro de los candidatos. */
    private function pickCol(string $table, array $candidates): ?string
    {
        if (!Schema::hasTable($table)) return null;
        $realCols = Schema::getColumnListing($table);
        $have = array_map('strtolower', $realCols);
        foreach ($candidates as $c) {
            $lc = strtolower($c);
            $idx = array_search($lc, $have, true);
            if ($idx !== false) {
                return $realCols[$idx]; // casing real
            }
        }
        return null;
    }

    /** Fuzzy por contiene */
    private function fuzzyCol(array $cols, array $needles): ?string
    {
        foreach ($cols as $c) {
            $lc = strtolower($c);
            foreach ($needles as $n) {
                if (strpos($lc, $n) !== false) return $c;
            }
        }
        return null;
    }

    /** Introspección de triggers: extrae columnas NEW.<col> usadas en triggers de la tabla usuario. */
    private function triggerRutCols(): array
    {
        try {
            $dbName = DB::getDatabaseName();
            $rows = DB::table('INFORMATION_SCHEMA.TRIGGERS')
                ->select('TRIGGER_NAME','ACTION_STATEMENT')
                ->where('TRIGGER_SCHEMA', $dbName)
                ->where('EVENT_OBJECT_TABLE', self::TBL_USERS)
                ->get();

            $rutCol = null; $dvCol = null;

            foreach ($rows as $t) {
                $body = (string)($t->ACTION_STATEMENT ?? '');
                if (preg_match_all('/NEW\.\s*`?([A-Za-z0-9_]+)`?/i', $body, $m)) {
                    foreach ($m[1] as $col) {
                        $lc = strtolower($col);
                        if (!$rutCol && (str_contains($lc,'rut') || str_contains($lc,'run') || str_contains($lc,'documento') || str_contains($lc,'dni'))) {
                            $rutCol = $col;
                        }
                        if (!$dvCol && (str_contains($lc,'dv') || str_contains($lc,'digito'))) {
                            $dvCol = $col;
                        }
                    }
                }
            }
            if ($rutCol || $dvCol) {
                Log::info('USERS.triggerCols', ['rut'=>$rutCol, 'dv'=>$dvCol]);
            }
            return [$rutCol, $dvCol];
        } catch (\Throwable $e) {
            Log::warning('USERS.triggerCols:inspect_failed', ['msg'=>$e->getMessage()]);
            return [null, null];
        }
    }

    /** Mapa final de columnas reales presentes (exact → fuzzy → triggers). */
    private function colmap(): array
    {
        $map = [
            'pk'       => $this->pickCol(self::TBL_USERS, self::COL_CANDS['pk']),
            'email'    => $this->pickCol(self::TBL_USERS, self::COL_CANDS['email']),
            'tipo'     => $this->pickCol(self::TBL_USERS, self::COL_CANDS['tipo']),
            'rut'      => $this->pickCol(self::TBL_USERS, self::COL_CANDS['rut']),
            'dv'       => $this->pickCol(self::TBL_USERS, self::COL_CANDS['dv']),
            'activo'   => $this->pickCol(self::TBL_USERS, self::COL_CANDS['activo']),
            'nombres'  => $this->pickCol(self::TBL_USERS, self::COL_CANDS['nombres']),
            'apellidos'=> $this->pickCol(self::TBL_USERS, self::COL_CANDS['apellidos']),
            'telefono' => $this->pickCol(self::TBL_USERS, self::COL_CANDS['telefono']),
            'direccion'=> $this->pickCol(self::TBL_USERS, self::COL_CANDS['direccion']),
            'password' => $this->pickCol(self::TBL_USERS, self::COL_CANDS['password']),
            'created'  => $this->pickCol(self::TBL_USERS, self::COL_CANDS['created']),
            'updated'  => $this->pickCol(self::TBL_USERS, self::COL_CANDS['updated']),
        ];

        try {
            $cols = Schema::getColumnListing(self::TBL_USERS);
            Log::info('USERS.table_columns', $cols);
        } catch (\Throwable $e) {
            Log::warning('USERS.table_columns:error', ['msg'=>$e->getMessage()]);
        }

        // Fuzzy si falta rut/dv
        if (!$map['rut'] || !$map['dv']) {
            $all = Schema::hasTable(self::TBL_USERS) ? Schema::getColumnListing(self::TBL_USERS) : [];
            if (!$map['rut']) $map['rut'] = $this->fuzzyCol($all, ['rut','run','documento','dni']);
            if (!$map['dv'])  $map['dv']  = $this->fuzzyCol($all, ['dv','digito']);
        }

        // Introspección de triggers si todavía falta
        if ((!$map['rut'] || !$map['dv'])) {
            [$trRut, $trDv] = $this->triggerRutCols();
            if (!$map['rut'] && $trRut) $map['rut'] = $trRut;
            if (!$map['dv']  && $trDv)  $map['dv']  = $trDv;
        }

        Log::info('USERS.colmap.final', $map);
        return $map;
    }

    /** Normaliza entrada de RUT y DV (permite “12.345.678-K”). */
    private function splitRut(?string $rutInput, ?string $dvInput): array
    {
        $rut = preg_replace('/[^0-9]/', '', (string)$rutInput);
        $dv  = strtoupper((string)$dvInput);

        $combo = trim((string)$rutInput.'-'.(string)$dvInput, '-');
        if (!$rut && preg_match('/^([0-9\.\-]+)\-([0-9kK])$/', $combo, $m)) {
            $rut = preg_replace('/[^0-9]/', '', $m[1] ?? '');
            $dv  = strtoupper($m[2] ?? '');
        }
        return [$rut, $dv];
    }

    /** Valida formato simple de RUT y DV. (el trigger hará la validación dura) */
    private function assertRutDv(string $rut, string $dv): void
    {
        if ($rut !== '' && !preg_match('/^[0-9]{6,9}$/', $rut)) {
            abort(422, 'RUT inválido: usa 6–9 dígitos, sin puntos ni guion.');
        }
        if ($dv !== '' && !preg_match('/^[0-9K]$/i', $dv)) {
            abort(422, 'DV inválido: debe ser 0-9 o K.');
        }
    }

    /** Vinculación UNIDAD→CONDOMINIO (para listados) */
    private function unidadCondoColumn(): ?string
    {
        if (!Schema::hasTable('unidad')) return null;
        $cols = array_map('strtolower', Schema::getColumnListing('unidad'));
        $candidatos = ['id_condominio','condominio_id','id_condo','id_edificio','edificio_id'];
        foreach ($candidatos as $c) if (in_array(strtolower($c), $cols, true)) return $c;
        foreach ($cols as $c) if (str_ends_with($c, '_condominio')) return $c;
        return null;
    }

    /** Condominios permitidos para el usuario actual */
    private function allowedCondoIds(): array
    {
        $u    = auth()->user();
        $role = $u->rol ?? ($u->tipo_usuario ?? null);

        if ($role === 'super_admin') {
            return DB::table('condominio')->pluck('id_condominio')->map(fn($v)=>(int)$v)->all();
        }

        $ids = [];
        if (Schema::hasTable('admin_user_condo')) {
            $ids = DB::table('admin_user_condo')
                ->where('id_usuario', $u->id_usuario ?? $u->id)
                ->pluck('id_condominio')->map(fn($v)=>(int)$v)->all();
        }
        $ctx = session('ctx_condo_id') ?? session('ctx_condominio_id');
        if ($ctx) $ids[] = (int)$ctx;

        return array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));
    }

    /** Panel */
    public function index(Request $r)
    {
        $yo     = auth()->user();
        $role   = $yo->rol ?? ($yo->tipo_usuario ?? null);
        $isSA   = $role === 'super_admin';
        $allowedIds = $this->allowedCondoIds();

        $condos = $isSA
            ? DB::table('condominio')->orderBy('nombre')->get()
            : (empty($allowedIds)
                ? collect()
                : DB::table('condominio')->whereIn('id_condominio', $allowedIds)->orderBy('nombre')->get());

        $idCondo = (int)($r->query('id_condominio') ?: (session('ctx_condo_id') ?? session('ctx_condominio_id') ?? 0));
        if ($isSA) {
            if (!$idCondo) $idCondo = (int)($condos->first()->id_condominio ?? 0);
        } else {
            if (!$idCondo && $condos->count()) $idCondo = (int)$condos->first()->id_condominio;
            if ($idCondo && !in_array($idCondo, $allowedIds, true)) {
                $idCondo = (int)($condos->first()->id_condominio ?? 0);
            }
        }
        if ($idCondo === 0) $idCondo = null;

        $unidades = collect();
        $uCondoCol = $this->unidadCondoColumn();
        if ($idCondo && $uCondoCol && Schema::hasTable('unidad')) {
            $unidades = DB::table('unidad')
                ->where($uCondoCol, $idCondo)
                ->orderByRaw("COALESCE(codigo, CAST(id_unidad AS CHAR))")
                ->select('id_unidad','codigo')
                ->get();
        }

        $cm = $this->colmap();
        $usuarios = collect();
        if ($idCondo) {
            $adminIds = [];
            if (Schema::hasTable('admin_user_condo')) {
                $adminIds = DB::table('admin_user_condo')
                    ->where('id_condominio', $idCondo)
                    ->pluck('id_usuario')
                    ->all();
            }

            $resIds = [];
            if ($uCondoCol && Schema::hasTable('residencia') && Schema::hasTable('unidad')) {
                $resIds = DB::table('residencia as r')
                    ->join('unidad as u', 'u.id_unidad', '=', 'r.id_unidad')
                    ->where("u.$uCondoCol", $idCondo)
                    ->pluck('r.id_usuario')
                    ->all();
            }

            $coproIds = [];
            if ($uCondoCol && Schema::hasTable('copropietario') && Schema::hasTable('unidad')) {
                $coproIds = DB::table('copropietario as c')
                    ->join('unidad as u', 'u.id_unidad', '=', 'c.id_unidad')
                    ->where("u.$uCondoCol", $idCondo)
                    ->pluck('c.id_usuario')
                    ->all();
            }

            $ids = array_values(array_unique(array_merge($adminIds, $resIds, $coproIds)));
            if (!empty($ids)) {
                $usuarios = DB::table(self::TBL_USERS)
                    ->whereIn($cm['pk'] ?? 'id_usuario', $ids)
                    ->orderBy($cm['nombres'] ?? 'nombres')
                    ->limit(600)
                    ->get();
            }
        } else {
            $usuarios = ($role === 'super_admin')
                ? DB::table(self::TBL_USERS)->orderBy($cm['nombres'] ?? 'nombres')->limit(600)->get()
                : collect();
        }

        $pendientes = DB::table('usuario as u')
            ->leftJoin('residencia as r', 'r.id_usuario', '=', 'u.id_usuario')
            ->whereNull('r.id_usuario')
            ->whereIn('u.tipo_usuario', ['residente','copropietario'])
            ->orderBy('u.id_usuario', 'desc')
            ->limit(30)
            ->get();

        return view('admin_usuarios', [
            'usuarios'    => $usuarios,
            'users'       => $usuarios,
            'condosCombo' => $condos,
            'condos'      => $condos,
            'idCondo'     => $idCondo,
            'unidades'    => $unidades,
            'pendientes'  => $pendientes,
        ]);
    }

    /** Alta de usuario */
    public function store(Request $r)
    {
        $cm = $this->colmap();

        // Validaciones base
        $rules = [
            'tipo_usuario' => 'required|in:residente,copropietario,admin,super_admin',
            'nombres'      => 'required|string|max:80',
            'apellidos'    => 'required|string|max:80',
            'email'        => 'required|email|max:120|unique:'.self::TBL_USERS.','.($cm['email'] ?? 'email'),
            'telefono'     => 'nullable|string|max:30',
            'direccion'    => 'nullable|string|max:150',
        ];

        // Si existe columna de contraseña (p.ej. pass_hash), es obligatoria
        if ($cm['password']) {
            $rules['password'] = 'required|string|min:6';
        }

        // Si hay columnas de RUT o triggers que las exigen, pedimos rut/dv del formulario
        if ($cm['rut'] || $cm['dv']) {
            $rules['rut'] = 'required';
            if ($cm['dv']) $rules['dv'] = 'required';
        }

        // Validar
        $d = $r->validate($rules);

        // Preparar RUT/DV si corresponde
        $rutCol = $cm['rut'];
        $dvCol  = $cm['dv'];
        $rut = ''; $dv = ''; $rutFull = null;
        if ($rutCol || $dvCol) {
            [$rut, $dv] = $this->splitRut($r->input('rut'), $r->input('dv'));
            $this->assertRutDv((string)$rut, (string)$dv);
            $rutFull = $rut.'-'.strtoupper($dv);
        }

        $insert = [];
        if ($cm['tipo'])      $insert[$cm['tipo']]      = $d['tipo_usuario'];
        if ($cm['nombres'])   $insert[$cm['nombres']]   = $d['nombres'];
        if ($cm['apellidos']) $insert[$cm['apellidos']] = $d['apellidos'];
        if ($cm['email'])     $insert[$cm['email']]     = $d['email'];
        if ($cm['telefono'])  $insert[$cm['telefono']]  = $d['telefono'] ?? null;
        if ($cm['direccion']) $insert[$cm['direccion']] = $d['direccion'] ?? null;
        if ($cm['activo'])    $insert[$cm['activo']]    = 1;

        // Incluir RUT/DV detectados (una o dos columnas)
        if ($rutCol && $dvCol) {
            $insert[$rutCol] = $rut;
            $insert[$dvCol]  = strtoupper($dv);
        } elseif ($rutCol && !$dvCol) {
            $insert[$rutCol] = $rutFull; // única columna rut-like → NNNNNNNN-D
        } elseif (!$rutCol && $dvCol) {
            $insert[$dvCol]  = strtoupper($dv);
        }

        // Password (pass_hash)
        if ($cm['password']) {
            $insert[$cm['password']] = Hash::make($d['password']);
        }

        // created_at/creado_at si existe y no tiene default
        if ($cm['created']) {
            $insert[$cm['created']] = now();
        }

        DB::beginTransaction();
        try {
            Log::info('USERS.store:insert_cols', array_keys($insert));
            $id = DB::table(self::TBL_USERS)->insertGetId($insert);

            // Si es admin y existe pivote, asignar condominio de contexto (opcional)
            if (($d['tipo_usuario'] ?? '') === 'admin' && Schema::hasTable('admin_user_condo')) {
                $ctx = (int)(session('ctx_condo_id') ?? session('ctx_condominio_id') ?? 0);
                if ($ctx) {
                    DB::table('admin_user_condo')->insert([
                        'id_usuario'    => $id,
                        'id_condominio' => $ctx,
                    ]);
                }
            }

            DB::commit();
            return back()->with('ok', 'Usuario creado correctamente. Si es residente/copropietario, vincúlalo a una unidad en Residencias.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $msg = $e->getMessage();

            if (str_contains($msg, 'SQLSTATE[45000]') && str_contains($msg, 'RUT')) {
                Log::error('USERS.store:error_trigger', ['msg' => $msg, 'insert_cols' => array_keys($insert)]);
                return back()->with('err', 'El trigger de la BD rechazó el RUT/DV. Verifica que el RUT y DV sean válidos y en el formato correcto.')->withInput();
            }

            Log::error('USERS.store:error', ['msg' => $msg, 'insert_cols' => array_keys($insert)]);
            return back()->with('err', $msg)->withInput();
        }
    }

    /** Edita datos (sin tocar residencias). */
    public function update($id, Request $r)
    {
        $cm = $this->colmap();

        $rules = [
            'tipo_usuario' => 'required|in:residente,copropietario,admin,super_admin',
            'nombres'      => 'required|string|max:80',
            'apellidos'    => 'required|string|max:80',
            'telefono'     => 'nullable|string|max:30',
            'direccion'    => 'nullable|string|max:150',
        ];
        if ($cm['password']) $rules['password'] = 'nullable|string|min:6';

        $d = $r->validate($rules);

        $upd = [];
        if ($cm['tipo'])      $upd[$cm['tipo']]      = $d['tipo_usuario'];
        if ($cm['nombres'])   $upd[$cm['nombres']]   = $d['nombres'];
        if ($cm['apellidos']) $upd[$cm['apellidos']] = $d['apellidos'];
        if ($cm['telefono'])  $upd[$cm['telefono']]  = $d['telefono'] ?? null;
        if ($cm['direccion']) $upd[$cm['direccion']] = $d['direccion'] ?? null;
        if ($cm['password'] && !empty($d['password'])) {
            $upd[$cm['password']] = Hash::make($d['password']);
        }

        DB::beginTransaction();
        try {
            DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->update($upd);
            DB::commit();
            return back()->with('ok', 'Usuario actualizado.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err', $e->getMessage())->withInput();
        }
    }

    /** Resetea password con una clave temporal. */
    public function resetPassword($id)
    {
        $cm = $this->colmap();
        $u = DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->first();
        if (!$u) return back()->with('err', 'Usuario no encontrado.');

        $tipoCol = $cm['tipo'] ?? 'tipo_usuario';
        $yo = auth()->user();
        $yoIsSA = ($yo->tipo_usuario ?? ($yo->rol ?? '')) === 'super_admin';
        $esSAu  = (($u->{$tipoCol} ?? '') === 'super_admin');
        if ($esSAu && !$yoIsSA) return back()->with('err', 'No autorizado.');

        if (!($cm['password'])) return back()->with('err','No existe columna de contraseña.');

        $tmp = Str::random(10);
        DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->update([$cm['password'] => Hash::make($tmp)]);

        return back()->with('ok', 'Contraseña temporal: '.$tmp);
    }

    /** Activa / desactiva usuario. */
    public function toggle($id)
    {
        $cm = $this->colmap();
        $u = DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->first();
        if (!$u) return back()->with('err', 'Usuario no encontrado.');

        $yo = auth()->user();
        $yoId = $yo->id_usuario ?? $yo->id ?? null;
        if ($yoId == $id) return back()->with('err', 'No puedes desactivar tu propio usuario.');

        $tipoCol = $cm['tipo'] ?? 'tipo_usuario';
        $yoIsSA = ($yo->tipo_usuario ?? ($yo->rol ?? '')) === 'super_admin';
        $esSAu  = (($u->{$tipoCol} ?? '') === 'super_admin');
        if ($esSAu && !$yoIsSA) return back()->with('err', 'No autorizado.');

        if (!($cm['activo'])) return back()->with('err','No existe columna de activo.');

        $nuevo = (int)($u->{$cm['activo']} ?? 0) ? 0 : 1;
        DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->update([$cm['activo'] => $nuevo]);

        return back()->with('ok', $nuevo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    /** Elimina usuario (y limpia pivot admin_user_condo si aplica). */
    public function destroy($id)
    {
        $cm = $this->colmap();
        $u = DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->first();
        if (!$u) return back()->with('err', 'Usuario no encontrado.');

        $yo = auth()->user();
        $yoId = $yo->id_usuario ?? $yo->id ?? null;
        if ($yoId == $id) return back()->with('err', 'No puedes eliminar tu propio usuario.');

        $tipoCol = $cm['tipo'] ?? 'tipo_usuario';
        $yoIsSA = ($yo->tipo_usuario ?? ($yo->rol ?? '')) === 'super_admin';
        $esSAu  = (($u->{$tipoCol} ?? '') === 'super_admin');
        if ($esSAu && !$yoIsSA) return back()->with('err', 'No autorizado.');

        DB::beginTransaction();
        try {
            if (Schema::hasTable('admin_user_condo')) {
                DB::table('admin_user_condo')->where('id_usuario', (int)$id)->delete();
            }
            DB::table(self::TBL_USERS)->where($cm['pk'] ?? 'id_usuario', (int)$id)->delete();

            DB::commit();
            return back()->with('ok', 'Usuario eliminado.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err', $e->getMessage());
        }
    }
}
