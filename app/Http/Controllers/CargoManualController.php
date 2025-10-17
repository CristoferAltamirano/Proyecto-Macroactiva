<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AuditoriaService;
use Carbon\Carbon;

class CargoManualController extends Controller
{
    public function index()
    {
        $ultU = DB::table('cargo_unidad')->orderByDesc('id_cargo_uni')->limit(60)->get();
        $ultI = DB::table('cargo_individual')->orderByDesc('id_cargo_indv')->limit(60)->get();
        $conceptos = DB::table('cat_concepto_cargo')->orderBy('nombre')->get();

        return view('cargos_manual', compact('ultU','ultI','conceptos'));
    }

    public function storeCargoUnidad(Request $r)
    {
        $d = $r->validate([
            'id_unidad'          => ['required','integer'],
            'periodo'            => ['required','regex:/^[0-9]{6}$/'],
            'id_concepto_cargo'  => ['required','integer'],
            'tipo'               => ['required','in:normal,extra,ajuste'],
            'monto'              => ['required','numeric','min:0'],
            'detalle'            => ['nullable','string','max:300'],
        ]);

        DB::beginTransaction();
        try {
            // Insert del cargo
            $id = DB::table('cargo_unidad')->insertGetId($d);

            // Auditoría de creación
            AuditoriaService::log('cargo_unidad', $id, 'CREAR', $d);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            AuditoriaService::log('cargo_unidad', 0, 'ERROR_CREAR', [
                'msg'     => $e->getMessage(),
                'payload' => $d,
            ]);
            return back()->with('err', 'No se pudo crear el cargo de unidad: '.$e->getMessage())
                         ->withInput();
        }

        // Determinar condominio y regenerar cobros (fuera de la transacción)
        $condo = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('u.id_unidad',$d['id_unidad'])
            ->value('g.id_condominio');

        AuditoriaService::log('cobro', 0, 'REGENERAR_DESDE_CARGOS', [
            'origen'        => 'cargo_unidad',
            'id_cargo'      => $id,
            'id_condominio' => (int)$condo,
            'periodo'       => $d['periodo'],
        ]);

        try {
            \App\Services\CobroService::generarDesdeCargos($d['periodo'], (int)$condo);
        } catch (\Throwable $e) {
            AuditoriaService::log('cobro', 0, 'REGENERAR_ERROR', [
                'origen'        => 'cargo_unidad',
                'id_cargo'      => $id,
                'id_condominio' => (int)$condo,
                'periodo'       => $d['periodo'],
                'msg'           => $e->getMessage(),
            ]);
            return back()->with('ok', "Cargo unidad #$id creado, pero hubo un problema al actualizar cobros.")
                         ->with('warn', $e->getMessage());
        }

        return back()->with('ok',"Cargo unidad #$id creado y cobro actualizado.");
    }

    public function storeCargoIndividual(Request $r)
    {
        $d = $r->validate([
            'id_unidad'  => ['required','integer'],
            'periodo'    => ['required','regex:/^[0-9]{6}$/'],
            'tipo'       => ['required','string','max:30'],
            'referencia' => ['nullable','string','max:60'],
            'monto'      => ['required','numeric','min:0'],
            'detalle'    => ['nullable','string','max:300'],
        ]);

        DB::beginTransaction();
        try {
            // Insert del cargo individual
            $id = DB::table('cargo_individual')->insertGetId($d);

            // Auditoría de creación
            AuditoriaService::log('cargo_individual', $id, 'CREAR', $d);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            AuditoriaService::log('cargo_individual', 0, 'ERROR_CREAR', [
                'msg'     => $e->getMessage(),
                'payload' => $d,
            ]);
            return back()->with('err', 'No se pudo crear el cargo individual: '.$e->getMessage())
                         ->withInput();
        }

        // Determinar condominio
        $condo = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('u.id_unidad',$d['id_unidad'])
            ->value('g.id_condominio');

        // Regenerar cobros (para que aparezca en la deuda)
        AuditoriaService::log('cobro', 0, 'REGENERAR_DESDE_CARGOS', [
            'origen'        => 'cargo_individual',
            'id_cargo'      => $id,
            'id_condominio' => (int)$condo,
            'periodo'       => $d['periodo'],
        ]);
        try {
            \App\Services\CobroService::generarDesdeCargos($d['periodo'], (int)$condo);
        } catch (\Throwable $e) {
            AuditoriaService::log('cobro', 0, 'REGENERAR_ERROR', [
                'origen'        => 'cargo_individual',
                'id_cargo'      => $id,
                'id_condominio' => (int)$condo,
                'periodo'       => $d['periodo'],
                'msg'           => $e->getMessage(),
            ]);
            // seguimos, el devengo puede registrarse igual
        }

        // ===== DEVENGO AUTOMÁTICO EN LIBRO (multa/mora/recargo/interes) =====
        // Debe: 1201 (CxC) | Haber: 4202 (multas/recargos) o 4203 (intereses) | fallback 4101 si no existen.
        try {
            if (class_exists(\App\Services\LibroService::class)) {
                $tipo = strtolower(trim($d['tipo']));

                // Fecha = último día del período (AAAAMM)
                $yyyy = (int)substr($d['periodo'], 0, 4);
                $mm   = (int)substr($d['periodo'], 4, 2);
                $fechaDevengo = Carbon::createFromDate($yyyy, $mm, 1)->endOfMonth()->toDateString();

                // Helper de existencia de cuenta
                $exists = fn(string $codigo) => DB::table('cuenta_contable')->where('codigo', $codigo)->exists();

                // Verificamos CxC 1201 (si no está, registramos auditoría y omitimos asiento)
                if (!$exists('1201')) {
                    AuditoriaService::log('libro', 0, 'DEVENGO_SKIP_SIN_1201', [
                        'cargo_individual' => $id,
                        'id_condominio'    => (int)$condo,
                        'periodo'          => $d['periodo'],
                        'monto'            => (float)$d['monto'],
                    ]);
                } else {
                    // Elegir cuenta de ingreso
                    $ctaIngMulta   = $exists('4202') ? '4202' : '4101';
                    $ctaIngInteres = $exists('4203') ? '4203' : ($exists('4202') ? '4202' : '4101');

                    $map = [
                        'multa'   => $ctaIngMulta,
                        'mora'    => $ctaIngMulta,
                        'recargo' => $ctaIngMulta,
                        'interes' => $ctaIngInteres,
                    ];

                    if (isset($map[$tipo])) {
                        $glosa = 'Cargo indv '.$tipo
                               . ' periodo '.$d['periodo']
                               . (!empty($d['referencia']) ? (' ref '.$d['referencia']) : '');

                        \App\Services\LibroService::asiento(
                            (int)$condo,
                            $fechaDevengo,
                            '1201',            // Debe CxC
                            $map[$tipo],       // Haber ingreso
                            (float)$d['monto'],
                            'cargo_individual',
                            (int)$id,
                            $glosa
                        );

                        AuditoriaService::log('libro', 0, 'DEVENGO_OK', [
                            'cargo_individual' => $id,
                            'id_condominio'    => (int)$condo,
                            'fecha'            => $fechaDevengo,
                            'debe'             => '1201',
                            'haber'            => $map[$tipo],
                            'monto'            => (float)$d['monto'],
                            'glosa'            => $glosa,
                        ]);
                    } else {
                        AuditoriaService::log('libro', 0, 'DEVENGO_IGNORADO_TIPO', [
                            'cargo_individual' => $id,
                            'tipo'             => $d['tipo'],
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DEVENGO ERROR', ['e' => $e->getMessage(), 'cargo_individual' => $id]);
            AuditoriaService::log('libro', 0, 'DEVENGO_ERROR', [
                'cargo_individual' => $id,
                'msg'               => $e->getMessage(),
            ]);
        }

        return back()->with('ok',"Cargo individual #$id creado, devengado y cobro actualizado.");
    }
}
