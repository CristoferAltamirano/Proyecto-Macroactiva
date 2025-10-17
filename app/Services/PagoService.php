<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PagoService
{
    public static function aplicarPago(int $idPago): void
    {
        $p = DB::table('pago')->where('id_pago',$idPago)->first();
        if (!$p || $p->monto<=0) return;

        DB::transaction(function() use($p){
            $rest = (float)$p->monto;
            $cobros = DB::table('cobro')
                ->where('id_unidad',$p->id_unidad)->where('saldo','>',0)
                ->orderBy('periodo')->lockForUpdate()->get();

            foreach ($cobros as $c) {
                if ($rest<=0) break;
                $apply = min($rest, (float)$c->saldo);
                if ($apply<=0) continue;

                DB::table('pago_aplicacion')->updateOrInsert(
                    ['id_pago'=>$p->id_pago,'id_cobro'=>$c->id_cobro],
                    ['monto_aplicado'=>$apply,'aplicado_at'=>now()]
                );
                $rest -= $apply;
                \App\Services\CobroService::recalcularTotales($c->id_cobro);
            }
        });

        \App\Services\LibroService::asientoPago($p->id_pago);
        \App\Services\FondoReservaService::abonarPorPago($p->id_pago);
        \App\Services\EmailService::enviarReciboPago($p->id_pago);
    }
}
