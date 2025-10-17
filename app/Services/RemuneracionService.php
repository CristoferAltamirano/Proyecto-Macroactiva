<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RemuneracionService
{
    /** Devenga remuneración (bruto): Debe 5102 / Haber 2102 */
    public static function devengar(int $idRemu): void
    {
        $r = DB::table('remuneracion')->where('id_remuneracion',$idRemu)->first();
        if(!$r) return;
        $idCondo = DB::table('trabajador')->where('id_trabajador',$r->id_trabajador)->value('id_condominio');
        if(!$idCondo) return;

        $bruto = (float)$r->bruto;
        if ($bruto<=0) return;

        \App\Services\LibroService::asiento((int)$idCondo, $r->fecha_pago ?? now(), '5102','2102',$bruto,'remuneracion',$r->id_remuneracion,'Devengo remuneración');
    }

    /** Paga líquido: Debe 2102 / Haber 1101 (Banco) */
    public static function pagar(int $idRemu): void
    {
        $r = DB::table('remuneracion')->where('id_remuneracion',$idRemu)->first();
        if(!$r) return;
        if(!$r->fecha_pago || (float)$r->liquido<=0) return;

        $idCondo = DB::table('trabajador')->where('id_trabajador',$r->id_trabajador)->value('id_condominio');
        if(!$idCondo) return;

        \App\Services\LibroService::asiento((int)$idCondo, $r->fecha_pago, '2102','1101',(float)$r->liquido,'remuneracion',$r->id_remuneracion,'Pago remuneración');
    }
}
