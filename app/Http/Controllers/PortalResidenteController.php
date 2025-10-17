<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Cobro;
use App\Models\Gasto;
use Barryvdh\DomPDF\Facade\Pdf;

class PortalResidenteController extends Controller
{
    /**
     * Muestra el detalle de un cobro específico.
     */
    public function showCobro($id)
    {
        $residente = auth()->guard('residente')->user();
        // Usamos with('unidad') para cargar la relación y evitar consultas N+1
        $cobro = Cobro::with('unidad')->findOrFail($id);

        // Seguridad: Asegurarse de que el cobro pertenece al residente autenticado
        if ($cobro->unidad_id !== $residente->id) {
            abort(403, 'Acceso no autorizado.');
        }

        // Obtener los gastos del mismo periodo para el desglose
        $gastosDelPeriodo = Gasto::where('periodo_gasto', $cobro->periodo)->get();

        return view('portal.cobro_detalle', [
            'cobro' => $cobro,
            'gastosDelPeriodo' => $gastosDelPeriodo,
        ]);
    }

    /**
     * Genera y descarga la boleta de un cobro en formato PDF.
     */
    public function descargarBoletaPDF($id)
    {
        $residente = auth()->guard('residente')->user();
        $cobro = Cobro::with('unidad')->findOrFail($id);

        // Seguridad
        if ($cobro->unidad_id !== $residente->id) {
            abort(403, 'Acceso no autorizado.');
        }

        $gastosDelPeriodo = Gasto::where('periodo_gasto', $cobro->periodo)->get();

        $pdf = Pdf::loadView('pdf.boleta_cobro', [
            'cobro' => $cobro,
            'gastosDelPeriodo' => $gastosDelPeriodo
        ]);

        // Formato del nombre del archivo: Boleta-Unidad101-2023-10.pdf
        $fileName = sprintf('Boleta-%s-%s.pdf', $cobro->unidad->numero, $cobro->periodo->format('Y-m'));

        return $pdf->download($fileName);
    }

    /**
     * Muestra el dashboard principal del residente.
     */
    public function dashboard()
    {
        $unidad = auth()->guard('residente')->user();
        $cobros = $unidad->cobros()->orderBy('periodo', 'desc')->get();
        $saldo_total_pendiente = $cobros->where('estado', 'pendiente')->sum('monto_total');

        return view('portal.dashboard', [
            'unidad' => $unidad,
            'cobros' => $cobros,
            'saldo_total_pendiente' => $saldo_total_pendiente,
        ]);
    }

    // ... (Manteniendo los helpers originales por si son usados en otras partes)

    private function resolvePersonaId($user): ?int
    {
        $email = trim((string)($user->email ?? ''));
        if (!Schema::hasTable('persona')) return null;
        if (isset($user->id_persona)) return (int) $user->id_persona;
        if ($email && Schema::hasColumn('persona', 'email')) {
            return (int) (DB::table('persona')->where('email',$email)->value('id_persona') ?? 0) ?: null;
        }
        return null;
    }

    private function resolveCopId(?int $uid, ?int $idPersona, string $email): ?int
    {
        if (!Schema::hasTable('copropietario')) return null;
        $cols = Schema::getColumnListing('copropietario');
        $idCandidates = ['id_copropietario','id_coprop','id_propietario','id_copro','id'];
        $idCol = null;
        foreach ($idCandidates as $c) {
            if (in_array($c, $cols, true)) { $idCol = $c; break; }
        }
        if (!$idCol) return null;
        $q = DB::table('copropietario');
        if ($uid && in_array('id_usuario', $cols, true)) {
            $q->where('id_usuario', $uid);
        } elseif ($idPersona && in_array('id_persona', $cols, true)) {
            $q->where('id_persona', $idPersona);
        } elseif ($email && in_array('email', $cols, true)) {
            $q->where('email', $email);
        } else {
            return null;
        }
        $row = $q->select($idCol)->first();
        return $row ? (int) $row->{$idCol} : null;
    }

    public function miCuenta()
    {
        $u = auth()->user();
        $uid = $u->id_usuario ?? $u->id;
        $email = trim((string)($u->email ?? ''));
        $idCondo = session('ctx_condo_id');
        $idPersona = $this->resolvePersonaId($u);
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
        $coprops = collect();
        if (Schema::hasTable('copropietario') && Schema::hasTable('unidad')) {
            $idCop = $this->resolveCopId($uid, $idPersona, $email);
            $pivot = null;
            if (Schema::hasTable('coprop_unidad')) $pivot = 'coprop_unidad';
            elseif (Schema::hasTable('copropietario_unidad')) $pivot = 'copropietario_unidad';
            if ($pivot && $idCop) {
                $q = DB::table($pivot.' as cu')
                    ->join('unidad as un','un.id_unidad','=','cu.id_unidad')
                    ->leftJoin('grupo as g','g.id_grupo','=','un.id_grupo')
                    ->leftJoin('condominio as c','c.id_condominio','=','g.id_condominio')
                    ->when($idCondo, fn($q)=>$q->where('g.id_condominio',$idCondo))
                    ->where('cu.id_copropietario',$idCop)
                    ->select(DB::raw('NULL as desde'), DB::raw('NULL as hasta'), 'un.codigo as unidad', 'un.id_unidad', DB::raw('COALESCE(c.nombre,"") as condominio'))
                    ->orderBy('un.codigo');
                if (Schema::hasColumn($pivot,'hasta')) {
                    $q->where(function($w) use ($pivot){
                        $w->whereNull('cu.hasta')->orWhere('cu.hasta','>=', now()->toDateString());
                    });
                }
                $coprops = $q->get();
            } else {
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

    public function estadoCuenta()
    {
        $u = auth()->user();
        $uid = $u->id_usuario ?? $u->id;
        $email = trim((string)($u->email ?? ''));
        $idCondo = session('ctx_condo_id');
        $idPersona = $this->resolvePersonaId($u);
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
        $idsCop = collect();
        if (Schema::hasTable('copropietario')) {
            $pivot = null;
            if (Schema::hasTable('coprop_unidad')) $pivot = 'coprop_unidad';
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
                'info' => $un,
                'abierto' => $abierto,
                'cobros' => $ultCobros,
                'pagos' => $ultPagos,
            ];
        }
        return view('portal_estado', compact('porUnidad'));
    }
}