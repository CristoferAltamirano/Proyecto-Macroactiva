<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FondoReservaService
{
    /**
     * Abona al FR un % del monto aplicado del pago (fallback al monto pagado si aún no hay aplicaciones).
     * Usa param_reglamento.recargo_fondo_reserva_pct del condominio.
     */
    public static function abonarPorPago(int $idPago): void
    {
        $pago = DB::table('pago')->where('id_pago',$idPago)->first();
        if(!$pago || (float)$pago->monto <= 0) return;

        $condo = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('u.id_unidad',$pago->id_unidad)
            ->value('g.id_condominio');

        if(!$condo) return;

        $pct = (float) (DB::table('param_reglamento')->where('id_condominio',$condo)->value('recargo_fondo_reserva_pct') ?? 0);
        if($pct <= 0) return;

        $aplicado = (float) DB::table('pago_aplicacion')->where('id_pago',$idPago)->sum('monto_aplicado');
        if($aplicado <= 0) $aplicado = (float)$pago->monto;

        $montoFR = round($aplicado * ($pct/100), 2);
        if($montoFR <= 0) return;

        DB::table('fondo_reserva_mov')->insert([
            'id_condominio' => (int)$condo,
            'fecha'         => $pago->fecha_pago,
            'tipo'          => 'abono',
            'periodo'       => $pago->periodo,
            'monto'         => $montoFR,
            'glosa'         => 'FR automático por pago ID '.$pago->id_pago.' ('.$pct.'%)',
        ]);
    }

    /**
     * Abona al FR el recargo % del periodo por condominio (según param_reglamento),
     * basado en la suma de total_cargos - total_descuentos de los cobros del periodo.
     * Idempotente por glosa: "FR recargo periodo {periodo}".
     */
    public static function abonarRecargoPeriodo(string $periodo, int $idCondominio): void
    {
        $pct = (float) (DB::table('param_reglamento')->where('id_condominio',$idCondominio)->value('recargo_fondo_reserva_pct') ?? 0);
        if ($pct <= 0) return;

        $base = (float) DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$idCondominio)
            ->where('c.periodo',$periodo)
            ->sum(DB::raw('(c.total_cargos - c.total_descuentos)'));

        $monto = round($base * ($pct/100), 2);
        if ($monto <= 0) return;

        $glosa = 'FR recargo periodo '.$periodo;

        // Idempotente: si ya existe, actualiza; si no, inserta.
        $existe = DB::table('fondo_reserva_mov')
            ->where('id_condominio',$idCondominio)
            ->where('tipo','abono')
            ->where('periodo',$periodo)
            ->where('glosa',$glosa)
            ->exists();

        $fecha = Carbon::createFromFormat('Ym',$periodo)->startOfMonth()->setTime(12,0);

        if ($existe) {
            DB::table('fondo_reserva_mov')
                ->where('id_condominio',$idCondominio)
                ->where('periodo',$periodo)
                ->where('glosa',$glosa)
                ->update(['monto'=>$monto,'fecha'=>$fecha]);
        } else {
            DB::table('fondo_reserva_mov')->insert([
                'id_condominio'=>$idCondominio,
                'fecha'=>$fecha,
                'tipo'=>'abono',
                'periodo'=>$periodo,
                'monto'=>$monto,
                'glosa'=>$glosa,
            ]);
        }
    }
}
