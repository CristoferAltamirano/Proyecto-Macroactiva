<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\LibroService;
use App\Services\AuditoriaService;

class GastoController extends Controller
{
    public function index()
    {
        $condos = DB::table('condominio')->orderBy('nombre')->get();
        $cats   = DB::table('gasto_categoria')->orderBy('nombre')->get();
        $docs   = DB::table('cat_doc_tipo')->orderBy('codigo')->get();
        $provs  = DB::table('proveedor')->orderBy('nombre')->limit(200)->get();

        $gastos = DB::table('gasto as g')
            ->join('condominio as c', 'c.id_condominio', '=', 'g.id_condominio')
            ->leftJoin('proveedor as p', 'p.id_proveedor', '=', 'g.id_proveedor')
            ->leftJoin('gasto_categoria as gc', 'gc.id_gasto_categ', '=', 'g.id_gasto_categ')
            ->select('g.*', 'c.nombre as condominio', 'p.nombre as proveedor', 'gc.nombre as categoria')
            ->orderByDesc('g.id_gasto')
            ->limit(50)
            ->get();

        return view('gastos_index', compact('gastos', 'condos', 'cats', 'docs', 'provs'));
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'id_condominio'  => ['required','integer'],
            'periodo'        => ['required','regex:/^[0-9]{6}$/'],
            'id_gasto_categ' => ['required','integer'],
            'id_proveedor'   => ['nullable','integer'],
            'id_doc_tipo'    => ['nullable','integer'],
            'documento_folio'=> ['nullable','string','max:40'],
            'fecha_emision'  => ['nullable','date'],
            'fecha_venc'     => ['nullable','date'],
            'neto'           => ['required','numeric','min:0'],
            'iva'            => ['required','numeric','min:0'],
            'descripcion'    => ['nullable','string','max:300'],
            'evidencia_url'  => ['nullable','url'],
        ]);

        return DB::transaction(function () use ($d) {
            // Inserta el gasto
            $idGasto = DB::table('gasto')->insertGetId($d);

            // 1) Nuevo mayor: LedgerService (si existe)
            try {
                if (class_exists('\App\Services\LedgerService')) {
                    \App\Services\LedgerService::asientoGasto($idGasto);
                }
            } catch (\Throwable $e) {
                Log::warning('LedgerService::asientoGasto falló, se continúa sin detener el flujo', [
                    'id_gasto' => $idGasto,
                    'err'      => $e->getMessage(),
                ]);
            }

            // 2) Mayor antiguo: LibroService (legacy) — a veces rompe por columna `cuenta` inexistente
            try {
                if (class_exists('\App\Services\LibroService')) {
                    LibroService::asientoGasto($idGasto);
                }
            } catch (\Throwable $e) {
                // No rompemos: sólo avisamos en el log para que puedas revisar
                Log::warning('LibroService::asientoGasto omitido (legacy mayor), probable columna `cuenta` inexistente', [
                    'id_gasto' => $idGasto,
                    'err'      => $e->getMessage(),
                ]);
            }

            // Auditoría
            try {
                AuditoriaService::log('gasto', $idGasto, 'CREAR', $d);
            } catch (\Throwable $e) {
                Log::warning('AuditoriaService::log falló al crear gasto', [
                    'id_gasto' => $idGasto,
                    'err'      => $e->getMessage(),
                ]);
            }

            return back()->with('ok', 'Gasto registrado.');
        });
    }
}
