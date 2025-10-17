<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Services\RemuneracionService;
use App\Services\AuditoriaService;

class RemuneracionController extends Controller
{
    public function index()
    {
        $trab = DB::table('trabajador as t')
            ->join('condominio as c','c.id_condominio','=','t.id_condominio')
            ->select('t.*','c.nombre as condominio')
            ->orderBy('c.nombre')->orderBy('t.apellidos')->get();

        $metodos = DB::table('cat_metodo_pago')->orderBy('nombre')->get();

        $remu = DB::table('remuneracion as r')
            ->join('trabajador as t','t.id_trabajador','=','r.id_trabajador')
            ->join('condominio as c','c.id_condominio','=','t.id_condominio')
            ->select('r.*','t.nombres','t.apellidos','c.nombre as condominio')
            ->orderByDesc('id_remuneracion')->limit(50)->get();

        return view('remuneraciones_index', compact('trab','metodos','remu'));
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'id_trabajador'   => ['required','integer'],
            'tipo'            => ['required','in:mensual,finiquito,bono,retroactivo,otro'],
            'periodo'         => ['required','regex:/^[0-9]{6}$/'],
            'bruto'           => ['required','numeric','min:0'],
            'imposiciones'    => ['required','numeric','min:0'],
            'descuentos'      => ['required','numeric','min:0'],
            'liquido'         => ['required','numeric','min:0'],
            'fecha_pago'      => ['nullable','date'],
            'id_metodo_pago'  => ['nullable','integer'],
            'comprobante_url' => ['nullable','url'],
            'observacion'     => ['nullable','string','max:300'],
        ]);

        $id = DB::table('remuneracion')->insertGetId($d);

        if (class_exists(RemuneracionService::class)) {
            try { RemuneracionService::devengar($id); } catch (\Throwable $e) {}
        }
        AuditoriaService::log('remuneracion', $id, 'CREAR', $d);

        $this->devengarAsientos($id);

        if (!empty($d['fecha_pago']) && (float)$d['liquido'] > 0) {
            if (class_exists(RemuneracionService::class)) {
                try { RemuneracionService::pagar($id); } catch (\Throwable $e) {}
            }
            AuditoriaService::log('remuneracion', $id, 'PAGAR', [
                'fecha_pago'     => $d['fecha_pago'],
                'id_metodo_pago' => $d['id_metodo_pago'] ?? null,
            ]);
            $this->pagoAsiento($id);
        }

        return back()->with('ok','Remuneración registrada.');
    }

    public function pagar(Request $r, int $id)
    {
        $data = $r->validate([
            'fecha_pago'     => ['required','date'],
            'id_metodo_pago' => ['required','integer'],
        ]);

        DB::table('remuneracion')->where('id_remuneracion',$id)->update($data);

        if (class_exists(RemuneracionService::class)) {
            try { RemuneracionService::pagar($id); } catch (\Throwable $e) {}
        }
        AuditoriaService::log('remuneracion', $id, 'PAGAR', $data);

        $this->pagoAsiento($id);

        return back()->with('ok','Pago de remuneración registrado.');
    }

    /**
     * ✅ NUEVO: Pago de retenciones (AFP/Isapre/previred etc.)
     * - Asiento: Debe 2107 (retenciones por pagar) / Haber 1101 (Banco)
     */
    public function pagarRetenciones(Request $r, int $id)
    {
        $data = $r->validate([
            'fecha' => ['required','date'],
        ]);

        // Busca remuneración + trabajador/condominio
        $rem = DB::table('remuneracion as r')
            ->join('trabajador as t','t.id_trabajador','=','r.id_trabajador')
            ->select('r.*','t.id_condominio','t.id_trabajador','t.nombres','t.apellidos')
            ->where('r.id_remuneracion',$id)->first();

        if (!$rem) return back()->with('err','Remuneración no encontrada.');

        $ret = (float)$rem->imposiciones + (float)$rem->descuentos;
        if ($ret <= 0) return back()->with('err','No hay retenciones a pagar en esta remuneración.');

        // Si tu service tiene este método, lo invocamos (no bloqueante)
        if (class_exists(RemuneracionService::class) && method_exists(RemuneracionService::class, 'pagarRetenciones')) {
            try { RemuneracionService::pagarRetenciones($id, $data['fecha']); } catch (\Throwable $e) {}
        }

        // Asiento contable 2107 / 1101
        if (class_exists(\App\Services\LibroService::class)) {
            $cid   = (int)$rem->id_condominio;
            $fecha = $data['fecha'];
            $per   = $rem->periodo ?? '';
            $glosa = "Pago retenciones remuneración $per Trabajador #{$rem->id_trabajador} ".trim(($rem->nombres ?? '').' '.($rem->apellidos ?? ''));

            try {
                \App\Services\LibroService::asiento(
                    $cid, $fecha, '2107', '1101', $ret,
                    'remuneracion_ret', $id, $glosa
                );
                AuditoriaService::log('remuneracion', $id, 'ASIENTO_PAGO_RET', [
                    'cta_debe' => '2107', 'cta_haber' => '1101', 'monto' => $ret, 'fecha' => $fecha
                ]);
            } catch (\Throwable $e) {
                Log::warning('Remu asiento pago retenciones falló (ignorado)', [
                    'id' => $id, 'e' => $e->getMessage()
                ]);
                AuditoriaService::log('remuneracion', $id, 'ASIENTO_PAGO_RET_ERROR', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Actualiza banderas/fechas si existen columnas (opcional, no bloqueante)
        $upd = [];
        if (Schema::hasColumn('remuneracion','ret_pagadas_at'))      $upd['ret_pagadas_at'] = $data['fecha'];
        if (Schema::hasColumn('remuneracion','ret_pagadas_monto'))   $upd['ret_pagadas_monto'] = $ret;
        if (Schema::hasColumn('remuneracion','ret_pagadas'))         $upd['ret_pagadas'] = 1;
        if (!empty($upd)) DB::table('remuneracion')->where('id_remuneracion',$id)->update($upd);

        AuditoriaService::log('remuneracion', $id, 'PAGAR_RETENCIONES', [
            'fecha' => $data['fecha'], 'monto' => $ret
        ]);

        return back()->with('ok','Pago de retenciones registrado.');
    }

    /* ===================== Helpers de asientos ===================== */

    private function devengarAsientos(int $idRemu): void
    {
        if (!class_exists(\App\Services\LibroService::class)) return;

        $r = DB::table('remuneracion as r')
            ->join('trabajador as t','t.id_trabajador','=','r.id_trabajador')
            ->select('r.*','t.id_condominio','t.id_trabajador','t.nombres','t.apellidos')
            ->where('r.id_remuneracion',$idRemu)->first();
        if (!$r) return;

        $cid   = (int)$r->id_condominio;
        $liq   = (float)$r->liquido;
        $ret   = (float)$r->imposiciones + (float)$r->descuentos;
        $tipo  = $r->tipo ?? 'mensual';
        $per   = $r->periodo;

        $anio  = (int)substr($per,0,4);
        $mes   = (int)substr($per,4,2);
        $fecha = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

        $glosaBase = "Remuneración $per ($tipo) Trabajador #{$r->id_trabajador} ".trim(($r->nombres ?? '').' '.($r->apellidos ?? ''));

        try {
            if ($liq > 0) {
                \App\Services\LibroService::asiento(
                    $cid, $fecha, '5101', '2106', $liq,
                    'remuneracion', $idRemu,
                    $glosaBase.' - Devengo líquido'
                );
                AuditoriaService::log('remuneracion', $idRemu, 'ASIENTO_DEVENGO', [
                    'cta_debe' => '5101', 'cta_haber' => '2106', 'monto' => $liq
                ]);
            }
            if ($ret > 0) {
                \App\Services\LibroService::asiento(
                    $cid, $fecha, '5101', '2107', $ret,
                    'remuneracion', $idRemu,
                    $glosaBase.' - Devengo retenciones'
                );
                AuditoriaService::log('remuneracion', $idRemu, 'ASIENTO_DEVENGO', [
                    'cta_debe' => '5101', 'cta_haber' => '2107', 'monto' => $ret
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Remu asiento devengo falló (ignorado)', [
                'id' => $idRemu, 'e' => $e->getMessage()
            ]);
            AuditoriaService::log('remuneracion', $idRemu, 'ASIENTO_DEVENGO_ERROR', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function pagoAsiento(int $idRemu): void
    {
        if (!class_exists(\App\Services\LibroService::class)) return;

        $r = DB::table('remuneracion as r')
            ->join('trabajador as t','t.id_trabajador','=','r.id_trabajador')
            ->select('r.*','t.id_condominio','t.id_trabajador','t.nombres','t.apellidos')
            ->where('r.id_remuneracion',$idRemu)->first();
        if (!$r) return;

        $cid   = (int)$r->id_condominio;
        $liq   = (float)$r->liquido;
        if ($liq <= 0) return;

        $fecha = $r->fecha_pago ?: now()->toDateString();
        $per   = $r->periodo ?? '';
        $glosa = "Pago remuneración $per Trabajador #{$r->id_trabajador} ".trim(($r->nombres ?? '').' '.($r->apellidos ?? ''));

        try {
            \App\Services\LibroService::asiento(
                $cid, $fecha, '2106', '1101', $liq,
                'remuneracion', $idRemu,
                $glosa
            );
            AuditoriaService::log('remuneracion', $idRemu, 'ASIENTO_PAGO', [
                'cta_debe' => '2106', 'cta_haber' => '1101', 'monto' => $liq
            ]);
        } catch (\Throwable $e) {
            Log::warning('Remu asiento pago falló (ignorado)', [
                'id' => $idRemu, 'e' => $e->getMessage()
            ]);
            AuditoriaService::log('remuneracion', $idRemu, 'ASIENTO_PAGO_ERROR', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
