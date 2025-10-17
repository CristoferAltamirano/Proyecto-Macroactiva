<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\AuditoriaService;

class FondoReservaController extends Controller
{
    public function index(Request $r)
    {
        // Rol y contexto actual
        $user  = auth()->user();
        $role  = $user->rol ?? ($user->tipo_usuario ?? null);
        $ctxId = session('ctx_condo_id');

        if ($role === 'super_admin') {
            // SUPER ADMIN: ve todos los condominios como siempre
            $condos  = DB::table('condominio')->orderBy('nombre')->get();
            $idCondo = $r->query('id_condominio') ?? ($condos->first()->id_condominio ?? null);
        } else {
            // ADMIN (de condominio): restringimos al condominio del contexto
            if ($ctxId) {
                $condos  = DB::table('condominio')->where('id_condominio', $ctxId)->get();
                $idCondo = (int) $ctxId;
            } else {
                // Fallback: no hay contexto -> tomamos el primero para no romper la vista
                $condos  = DB::table('condominio')->orderBy('nombre')->limit(1)->get();
                $idCondo = $condos->first()->id_condominio ?? null;
            }
        }

        $mov = collect();
        $resumen = [];

        if ($idCondo) {
            $mov = DB::table('fondo_reserva_mov')
                ->where('id_condominio', $idCondo)
                ->orderByDesc('fecha')
                ->limit(200)
                ->get();

            $resumen = DB::table('fondo_reserva_mov')
                ->selectRaw("DATE_FORMAT(fecha,'%Y-%m') as mes, SUM(CASE WHEN tipo='abono' THEN monto ELSE -monto END) as neto")
                ->where('id_condominio', $idCondo)
                ->groupBy('mes')
                ->orderBy('mes', 'desc')
                ->get();
        }

        return view('fr_panel', compact('condos', 'idCondo', 'mov', 'resumen'));
    }

    /**
     * Crear movimiento manual de Fondo de Reserva (abono/cargo).
     * Genera asiento contable por defecto (o si viene check "contabilizar"):
     * - Abono: Debe Banco(1101*) / Haber FR(3101*)
     * - Cargo: Debe FR(3101*) / Haber Banco(1101*)
     * (*) Si no existen esos códigos exactos, se buscarán alternativas por nombre y prefijos.
     */
    public function store(Request $r)
    {
        $d = $r->validate([
            'id_condominio' => ['nullable','integer','min:1'],
            'fecha'         => ['required','date'],
            'tipo'          => ['required','in:abono,cargo'],
            'periodo'       => ['nullable','regex:/^[0-9]{6}$/'],
            'monto'         => ['required','numeric','min:0.01'],
            'glosa'         => ['nullable','string','max:200'],
            'contabilizar'  => ['nullable','boolean'],
        ]);

        // Rol para decidir si forzamos el condominio del contexto
        $user = auth()->user();
        $role = $user->rol ?? ($user->tipo_usuario ?? null);

        // Condominio desde el form o contexto
        $cid = $d['id_condominio'] ?? session('ctx_condo_id');

        // Si NO es super_admin, forzamos SIEMPRE al contexto para evitar cruces
        if ($role !== 'super_admin' && session('ctx_condo_id')) {
            $cid = (int) session('ctx_condo_id');
        }

        if (!$cid) {
            $cid = DB::table('condominio')->orderBy('nombre')->value('id_condominio');
        }
        if (!$cid) {
            return back()->with('err','Debes seleccionar un condominio.')->withInput();
        }
        if (!Schema::hasTable('fondo_reserva_mov')) {
            return back()->with('err','No existe la tabla fondo_reserva_mov.')->withInput();
        }

        try {
            $row = [
                'id_condominio' => (int)$cid,
                'fecha'         => $d['fecha'],
                'tipo'          => $d['tipo'],
                'periodo'       => $d['periodo'] ?? null,
                'monto'         => round((float)$d['monto'], 2),
                'glosa'         => $d['glosa'] ?? null,
            ];

            // Insert del movimiento FR
            $idMov = DB::table('fondo_reserva_mov')->insertGetId($row);

            // Auditoría: creación del movimiento
            AuditoriaService::log('fondo_reserva_mov', $idMov, 'CREAR', $row);

            // === Contabilizar: por defecto TRUE salvo que el form lo desactive explícitamente ===
            $doLibro = $r->has('contabilizar') ? $r->boolean('contabilizar') : true;
            $warn = null;

            if ($doLibro) {
                $monto = (float)$row['monto'];
                $glosa = $row['glosa'] ? ('FR: '.$row['glosa']) : ('Movimiento FR ('.$d['tipo'].')');

                // Códigos preferidos
                $ctaBancoPrefer = '1101';
                $ctaFRPrefer    = '3101';

                // Resolver ID de cuentas de forma robusta
                [$idDebe, $idHaber, $ctaDebeCodResuelto, $ctaHaberCodResuelto, $resolverWarn] =
                    $this->resolverCuentasAsientoFR($d['tipo'], $ctaBancoPrefer, $ctaFRPrefer);

                if ($resolverWarn) {
                    $warn = $resolverWarn;
                }

                // 1) Intento con LibroService si hay codigos resueltos (por código, no por id)
                $serviceOk = false;
                if ($ctaDebeCodResuelto && $ctaHaberCodResuelto && class_exists(\App\Services\LibroService::class)) {
                    try {
                        \App\Services\LibroService::asiento(
                            (int)$cid,
                            $d['fecha'],
                            $ctaDebeCodResuelto,
                            $ctaHaberCodResuelto,
                            $monto,
                            'fr_mov',
                            (int)$idMov,
                            $glosa
                        );

                        $serviceOk = true;

                        AuditoriaService::log('fondo_reserva_mov', $idMov, 'ASIENTO_FR', [
                            'cta_debe'  => $ctaDebeCodResuelto,
                            'cta_haber' => $ctaHaberCodResuelto,
                            'monto'     => $monto,
                            'tipo'      => $d['tipo'],
                            'via'       => 'LibroService',
                        ]);
                    } catch (\Throwable $e) {
                        $warn = trim(($warn ? $warn.' | ' : '').'Asiento (LibroService) falló: '.$e->getMessage());
                        Log::warning('Asiento FR (LibroService) falló', ['e'=>$e->getMessage(), 'id_mov'=>$idMov]);
                        AuditoriaService::log('fondo_reserva_mov', $idMov, 'ASIENTO_FR_ERROR', [
                            'error' => $e->getMessage(),
                            'via'   => 'LibroService',
                        ]);
                    }
                }

                // 2) Fallback directo a libro_movimiento con IDs
                if (!$serviceOk) {
                    if (Schema::hasTable('libro_movimiento') && $idDebe && $idHaber) {
                        DB::table('libro_movimiento')->insert([
                            [
                                'id_condominio'   => (int)$cid,
                                'id_cta_contable' => $idDebe,
                                'fecha'           => $d['fecha'],
                                'glosa'           => $glosa,
                                'debe'            => $monto,
                                'haber'           => 0,
                                'ref_tabla'       => 'fr_mov',
                                'ref_id'          => (int)$idMov,
                            ],
                            [
                                'id_condominio'   => (int)$cid,
                                'id_cta_contable' => $idHaber,
                                'fecha'           => $d['fecha'],
                                'glosa'           => $glosa,
                                'debe'            => 0,
                                'haber'           => $monto,
                                'ref_tabla'       => 'fr_mov',
                                'ref_id'          => (int)$idMov,
                            ],
                        ]);

                        AuditoriaService::log('fondo_reserva_mov', $idMov, 'ASIENTO_FR', [
                            'cta_debe_id'  => $idDebe,
                            'cta_haber_id' => $idHaber,
                            'monto'        => $monto,
                            'tipo'         => $d['tipo'],
                            'via'          => 'fallback_libro_movimiento',
                        ]);
                    } else {
                        $warn = trim(($warn ? $warn.' | ' : '').'No se pudo escribir en libro_movimiento (IDs de cuentas no resueltos o tabla ausente).');
                        AuditoriaService::log('fondo_reserva_mov', $idMov, 'ASIENTO_FR_ERROR', [
                            'error' => 'IDs de cuentas no resueltos o tabla libro_movimiento ausente',
                            'via'   => 'fallback_libro_movimiento',
                        ]);
                    }
                }
            }

            return $warn
                ? back()->with('ok', 'Movimiento de Fondo de Reserva registrado.')->with('warn', $warn)
                : back()->with('ok', 'Movimiento de Fondo de Reserva registrado y contabilizado.');
        } catch (\Throwable $e) {
            return back()->with('err', 'No se pudo registrar el movimiento: '.$e->getMessage())->withInput();
        }
    }

    public function exportCsv(Request $r): StreamedResponse
    {
        $user = auth()->user();
        $role = $user->rol ?? ($user->tipo_usuario ?? null);

        $idCondo = (int)$r->validate(['id_condominio'=>['required','integer']])['id_condominio'];

        // Si NO es super_admin, forzamos el condominio al contexto
        if ($role !== 'super_admin' && session('ctx_condo_id')) {
            $idCondo = (int) session('ctx_condo_id');
        }

        $rows = DB::table('fondo_reserva_mov')
            ->where('id_condominio',$idCondo)
            ->orderBy('fecha')
            ->get(['fecha','tipo','periodo','monto','glosa']);

        // Auditoría export
        AuditoriaService::log('fondo_reserva_mov', $idCondo, 'EXPORT_CSV', ['rows' => $rows->count()]);

        $filename = 'fondo_reserva_'.$idCondo.'.csv';

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output','w');
            fputcsv($out, ['fecha','tipo','periodo','monto','glosa']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->fecha, $r->tipo, $r->periodo, $r->monto, $r->glosa]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resuelve cuentas para asiento de FR:
     * - $tipo: 'abono' o 'cargo'
     * - $ctaBancoPrefer / $ctaFRPrefer: códigos preferidos (p.ej. 1101 / 3101)
     *
     * Devuelve: [idDebe, idHaber, codigoDebe, codigoHaber, warnString]
     */
    private function resolverCuentasAsientoFR(string $tipo, string $ctaBancoPrefer, string $ctaFRPrefer): array
    {
        // Helpers
        $getIdByCodigo = function(string $cod) {
            return DB::table('cuenta_contable')->where('codigo', $cod)->value('id_cta_contable');
        };
        $getCodigoById = function($id) {
            return DB::table('cuenta_contable')->where('id_cta_contable',$id)->value('codigo');
        };

        // Buscar BANCO/CAJA
        $idBanco = $getIdByCodigo($ctaBancoPrefer);
        if (!$idBanco) {
            // Por nombre
            $cand = DB::table('cuenta_contable')
                ->where(function($q){
                    $q->where('nombre','like','%banco%')
                      ->orWhere('nombre','like','%caja%');
                })
                ->orderBy('codigo')
                ->first();
            if ($cand) $idBanco = $cand->id_cta_contable;
        }
        if (!$idBanco) {
            // Por prefijo código 11*
            $cand = DB::table('cuenta_contable')
                ->where('codigo','like','11%')
                ->orderBy('codigo')
                ->first();
            if ($cand) $idBanco = $cand->id_cta_contable;
        }

        // Buscar FONDO RESERVA
        $idFR = $getIdByCodigo($ctaFRPrefer);
        if (!$idFR) {
            // Por nombre
            $cand = DB::table('cuenta_contable')
                ->where(function($q){
                    $q->where('nombre','like','%fondo%reserva%')
                      ->orWhere('nombre','like','%reserva%');
                })
                ->orderBy('codigo')
                ->first();
            if ($cand) $idFR = $cand->id_cta_contable;
        }
        if (!$idFR) {
            // Por prefijo código 31*
            $cand = DB::table('cuenta_contable')
                ->where('codigo','like','31%')
                ->orderBy('codigo')
                ->first();
            if ($cand) $idFR = $cand->id_cta_contable;
        }

        $warn = null;
        if (!$idBanco) $warn = 'No se encontró cuenta de Banco/Caja (1101* / nombre ~ banco/caja / código 11*).';
        if (!$idFR) $warn = trim(($warn ? $warn.' ' : '').'No se encontró cuenta de Fondo de Reserva (3101* / nombre ~ reserva / código 31*).');

        // Mapear según tipo
        if ($tipo === 'abono') {
            $idDebe  = $idBanco;
            $idHaber = $idFR;
        } else {
            // cargo
            $idDebe  = $idFR;
            $idHaber = $idBanco;
        }

        $codigoDebe  = $idDebe  ? $getCodigoById($idDebe)  : null;
        $codigoHaber = $idHaber ? $getCodigoById($idHaber) : null;

        return [$idDebe, $idHaber, $codigoDebe, $codigoHaber, $warn];
    }
}
