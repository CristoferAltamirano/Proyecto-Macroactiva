<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalResidenteController extends Controller
{
    /** ===== Helpers ===== */

    /** Devuelve el id_persona del usuario si existe (por campo directo o por email). */
    private function resolvePersonaId($user): ?int
    {
        $email = trim((string)($user->email ?? ''));
        if (!Schema::hasTable('persona')) return null;

        if (isset($user->id_persona)) {
            return (int) $user->id_persona;
        }

        if ($email && Schema::hasColumn('persona', 'email')) {
            return (int) (DB::table('persona')->where('email',$email)->value('id_persona') ?? 0) ?: null;
        }
        return null;
    }

    /**
     * Resuelve el ID del copropietario en forma tolerante a distintos nombres de columna.
     * Intenta con id_usuario, id_persona o email (según lo que exista), y usa como
     * columna de retorno la primera que encuentre entre:
     *   id_copropietario, id_coprop, id_propietario, id_copro, id
     */
    private function resolveCopId(?int $uid, ?int $idPersona, string $email): ?int
    {
        if (!Schema::hasTable('copropietario')) return null;

        $cols = Schema::getColumnListing('copropietario');
        $idCandidates = ['id_copropietario','id_coprop','id_propietario','id_copro','id'];

        // ¿Cuál columna vamos a leer como ID?
        $idCol = null;
        foreach ($idCandidates as $c) {
            if (in_array($c, $cols, true)) { $idCol = $c; break; }
        }
        if (!$idCol) return null;

        // Armamos consulta tolerante según columnas disponibles
        $q = DB::table('copropietario');

        if ($uid && in_array('id_usuario', $cols, true)) {
            $q->where('id_usuario', $uid);
        } elseif ($idPersona && in_array('id_persona', $cols, true)) {
            $q->where('id_persona', $idPersona);
        } elseif ($email && in_array('email', $cols, true)) {
            $q->where('email', $email);
        } else {
            return null; // no tenemos cómo amarrar al usuario
        }

        $row = $q->select($idCol)->first();
        return $row ? (int) $row->{$idCol} : null;
    }

    /** ===== Vistas ===== */

    /** Mi cuenta */
    public function miCuenta()
    {
        $u         = auth()->user();
        $uid       = $u->id_usuario ?? $u->id;
        $email     = trim((string)($u->email ?? ''));
        $idCondo   = session('ctx_condo_id'); // puede ser null
        $idPersona = $this->resolvePersonaId($u);

        /* ========== RESIDENCIAS VIGENTES ========== */
        $residencias = collect();
        if (Schema::hasTable('residencia') && Schema::hasTable('unidad')) {
            $q = DB::table('residencia as r')
                ->join('unidad as un','un.id_unidad','=','r.id_unidad')
                ->leftJoin('grupo as g','g.id_grupo','=','un.id_grupo')
                ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
                ->when($idCondo, fn($q)=>$q->where('g.id_condominio',$idCondo))
                ->select('r.*','un.codigo as unidad','un.id_unidad', DB::raw('COALESCE(c.nombre,"") as condominio'))
                ->orderByDesc('r.desde');

            if (Schema::hasColumn('residencia','hasta')) {
                $q->where(function($w){
                    $w->whereNull('r.hasta')->orWhere('r.hasta','>=', now()->toDateString());
                });
            }

            $rCols = Schema::getColumnListing('residencia');
            if (in_array('id_usuario',$rCols,true)) {
                $q->where('r.id_usuario', $uid);
            } elseif ($idPersona && in_array('id_persona',$rCols,true)) {
                $q->where('r.id_persona', $idPersona);
            } elseif ($email && in_array('email',$rCols,true)) {
                $q->where('r.email', $email);
            } else {
                $q->whereRaw('1=0');
            }

            $residencias = $q->get();
        }

        /* ========== COPROPIETARIO VIGENTE (directo o pivote) ========== */
        $coprops = collect();

        if (Schema::hasTable('copropietario') && Schema::hasTable('unidad')) {
            $idCop = $this->resolveCopId($uid, $idPersona, $email);

            // ¿Hay pivote?
            $pivot = null;
            if (Schema::hasTable('coprop_unidad'))            $pivot = 'coprop_unidad';
            elseif (Schema::hasTable('copropietario_unidad')) $pivot = 'copropietario_unidad';

            if ($pivot && $idCop) {
                $q = DB::table($pivot.' as cu')
                    ->join('unidad as un','un.id_unidad','=','cu.id_unidad')
                    ->leftJoin('grupo as g','g.id_grupo','=','un.id_grupo')
                    ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
                    ->when($idCondo, fn($q)=>$q->where('g.id_condominio',$idCondo))
                    ->where('cu.id_copropietario',$idCop)
                    ->select(
                        DB::raw('NULL as desde'),
                        DB::raw('NULL as hasta'),
                        'un.codigo as unidad',
                        'un.id_unidad',
                        DB::raw('COALESCE(c.nombre,"") as condominio')
                    )
                    ->orderBy('un.codigo');

                if (Schema::hasColumn($pivot,'hasta')) {
                    $q->where(function($w) use ($pivot){
                        $w->whereNull('cu.hasta')->orWhere('cu.hasta','>=', now()->toDateString());
                    });
                }

                $coprops = $q->get();

            } else {
                // Sin pivote: modelo directo
                $q = DB::table('copropietario as cp')
                    ->join('unidad as un','un.id_unidad','=','cp.id_unidad')
                    ->leftJoin('grupo as g','g.id_grupo','=','un.id_grupo')
                    ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
                    ->when($idCondo, fn($q)=>$q->where('g.id_condominio',$idCondo))
                    ->select('cp.*','un.codigo as unidad','un.id_unidad', DB::raw('COALESCE(c.nombre,"") as condominio'))
                    ->orderByDesc('cp.desde');

                if (Schema::hasColumn('copropietario','hasta')) {
                    $q->where(function($w){
                        $w->whereNull('cp.hasta')->orWhere('cp.hasta','>=', now()->toDateString());
                    });
                }

                $cpCols = Schema::getColumnListing('copropietario');
                if (in_array('id_usuario',$cpCols,true)) {
                    $q->where('cp.id_usuario',$uid);
                } elseif ($idPersona && in_array('id_persona',$cpCols,true)) {
                    $q->where('cp.id_persona',$idPersona);
                } elseif ($email && in_array('email',$cpCols,true)) {
                    $q->where('cp.email',$email);
                } else {
                    $q->whereRaw('1=0');
                }

                $coprops = $q->get();
            }
        }

        return view('portal_cuenta', compact('u','residencias','coprops'));
    }

    /** Estado de cuenta con botón de pago */
    public function estadoCuenta()
    {
        $u         = auth()->user();
        $uid       = $u->id_usuario ?? $u->id;
        $email     = trim((string)($u->email ?? ''));
        $idCondo   = session('ctx_condo_id');
        $idPersona = $this->resolvePersonaId($u);

        // IDs por residencia
        $idsResid = collect();
        if (Schema::hasTable('residencia')) {
            $rCols = Schema::getColumnListing('residencia');
            $qr = DB::table('residencia as r')->select('r.id_unidad');

            if (in_array('hasta',$rCols,true)) {
                $qr->where(function($w){
                    $w->whereNull('r.hasta')->orWhere('r.hasta','>=', now()->toDateString());
                });
            }

            if (in_array('id_usuario',$rCols,true)) {
                $qr->where('r.id_usuario', $uid);
            } elseif ($idPersona && in_array('id_persona',$rCols,true)) {
                $qr->where('r.id_persona', $idPersona);
            } elseif ($email && in_array('email',$rCols,true)) {
                $qr->where('r.email', $email);
            } else {
                $qr->whereRaw('1=0');
            }

            $idsResid = $qr->pluck('id_unidad');
        }

        // IDs por copropietario (directo o pivote)
        $idsCop = collect();
        if (Schema::hasTable('copropietario')) {
            $pivot = null;
            if (Schema::hasTable('coprop_unidad'))            $pivot = 'coprop_unidad';
            elseif (Schema::hasTable('copropietario_unidad')) $pivot = 'copropietario_unidad';

            if ($pivot) {
                $idCop = $this->resolveCopId($uid, $idPersona, $email);
                if ($idCop) {
                    $qc = DB::table($pivot.' as cu')->select('cu.id_unidad')->where('cu.id_copropietario',$idCop);
                    if (Schema::hasColumn($pivot,'hasta')) {
                        $qc->where(function($w) use ($pivot){
                            $w->whereNull('cu.hasta')->orWhere('cu.hasta','>=', now()->toDateString());
                        });
                    }
                    $idsCop = $qc->pluck('id_unidad');
                }
            } else {
                $cpCols = Schema::getColumnListing('copropietario');
                $qc = DB::table('copropietario as cp')->select('cp.id_unidad');

                if (in_array('hasta',$cpCols,true)) {
                    $qc->where(function($w){
                        $w->whereNull('cp.hasta')->orWhere('cp.hasta','>=', now()->toDateString());
                    });
                }

                if (in_array('id_usuario',$cpCols,true)) {
                    $qc->where('cp.id_usuario',$uid);
                } elseif ($idPersona && in_array('id_persona',$cpCols,true)) {
                    $qc->where('cp.id_persona',$idPersona);
                } elseif ($email && in_array('email',$cpCols,true)) {
                    $qc->where('cp.email',$email);
                } else {
                    $qc->whereRaw('1=0');
                }

                $idsCop = $qc->pluck('id_unidad');
            }
        }

        $ids = $idsResid->concat($idsCop)->unique()->values();

        // Metadata de unidades (filtra por condominio activo si corresponde)
        $unidades = collect();
        if ($ids->isNotEmpty()) {
            $unidades = DB::table('unidad as u')
                ->leftJoin('grupo as g','g.id_grupo','=','u.id_grupo')
                ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
                ->when($idCondo, fn($q)=>$q->where('g.id_condominio',$idCondo))
                ->whereIn('u.id_unidad', $ids)
                ->select('u.id_unidad','u.codigo as unidad', DB::raw('COALESCE(c.nombre,"") as condominio'))
                ->orderBy('unidad')
                ->get();
        }

        $porUnidad = [];
        foreach ($unidades as $un) {
            $abierto = DB::table('cobro')
                ->where('id_unidad', $un->id_unidad)
                ->where('saldo','>',0)
                ->orderByDesc('periodo')
                ->first();

            $ultCobros = DB::table('cobro')
                ->where('id_unidad',$un->id_unidad)
                ->orderByDesc('periodo')
                ->limit(12)
                ->get();

            $ultPagos = DB::table('pago')
                ->where('id_unidad',$un->id_unidad)
                ->orderByDesc('fecha_pago')
                ->limit(8)
                ->get();

            $porUnidad[$un->id_unidad] = [
                'info'    => $un,
                'abierto' => $abierto,
                'cobros'  => $ultCobros,
                'pagos'   => $ultPagos,
            ];
        }

        return view('portal_estado', compact('porUnidad'));
    }
}
