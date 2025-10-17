<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ComprobantePagoService
{
    /**
     * Emite comprobante para un pago (si no existe) y retorna id_compr_pago.
     * Folio: "CP-AAAAMM-<id_pago>"; url_pdf: /comprobantes/{id}.pdf
     */
    public static function emitir(int $idPago): int
    {
        $existe = DB::table('comprobante_pago')->where('id_pago', $idPago)->first();
        if ($existe) return (int)$existe->id_compr_pago;

        $folio = 'CP-'.now()->format('Ym').'-'.$idPago;

        $id = (int) DB::table('comprobante_pago')->insertGetId([
            'id_pago'    => $idPago,
            'folio'      => $folio,
            'url_pdf'    => null, // se actualiza abajo
            'emitido_at' => now(),
        ]);

        $url = route('comprobante.pdf', $id);
        DB::table('comprobante_pago')->where('id_compr_pago',$id)->update(['url_pdf'=>$url]);

        // Auditoría
        \App\Services\AuditService::log('comprobante_pago', $id, 'CREAR', ['id_pago'=>$idPago,'folio'=>$folio]);

        return $id;
    }
}
