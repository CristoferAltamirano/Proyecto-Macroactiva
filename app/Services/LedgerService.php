<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    /**
     * Modo MANUAL:
     * - false (recomendado): NO inserta asientos desde PHP. Deja todo a cargo de los TRIGGERS de BD.
     * - true: inserta asientos manualmente (usa id_cta_contable, ref_tabla/ref_id).
     *   Si lo activas, DEBES desactivar los triggers para no duplicar.
     */
    private const MANUAL = false;

    /** Inserta un movimiento (sÃ³lo si MANUAL=true). $cuenta acepta '1101-Banco' o sÃ³lo '1101'. */
    public static function mov($fecha, $cuenta, $glosa, $debe, $haber, $refTabla, $refId, $idCondominio): void
    {
        if (!self::MANUAL) {
            // En modo triggers, no hacemos nada para evitar duplicados y errores de columnas antiguas.
            Log::debug('[LedgerService] mov() omitido (MANUAL=false, usando triggers).', [
                'ref' => $refTabla, 'ref_id' => $refId, 'glosa' => $glosa
            ]);
            return;
        }

        // Normaliza datetime
        $fechaDt = (is_string($fecha) && preg_match('/\d{2}:\d{2}/', $fecha))
            ? $fecha
            : ((string)$fecha).' 12:00:00';

        // Extrae cÃ³digo (antes del guiÃ³n) y busca id_cta_contable
        $codigo = explode('-', (string)$cuenta)[0];
        $idCuenta = DB::table('cuenta_contable')->where('codigo', $codigo)->value('id_cta_contable');

        if (!$idCuenta) {
            Log::warning('[LedgerService] Cuenta contable no encontrada', ['codigo' => $codigo, 'cuenta' => $cuenta]);
            return;
        }

        DB::table('libro_movimiento')->insert([
            'id_condominio'   => $idCondominio,
            'fecha'           => $fechaDt,
            'id_cta_contable' => $idCuenta,
            'debe'            => (float)$debe,
            'haber'           => (float)$haber,
            'ref_tabla'       => $refTabla,
            'ref_id'          => $refId,
            'glosa'           => $glosa,
        ]);
    }

    /** Asiento por pago (sÃ³lo si MANUAL=true). En triggers, se omite. */
    public static function pago(object $p): void
    {
        if (!self::MANUAL) return;

        $fec = substr((string)($p->fecha_pago ?? now()->toDateTimeString()), 0, 19);
        // Debe: Banco (1101), Haber: CxC (1201)
        self::mov($fec, '1101-Banco', 'Pago '.$p->id_pago, $p->monto, 0, 'pago', $p->id_pago, $p->id_condominio);
        self::mov($fec, '1201-CxC',   'Pago '.$p->id_pago, 0, $p->monto, 'pago', $p->id_pago, $p->id_condominio);
    }

    /** Asiento por gasto devengado (sÃ³lo si MANUAL=true). En triggers, se omite. */
    public static function gasto(int $idGasto): void
    {
        if (!self::MANUAL) return;

        $g = DB::table('gasto')->where('id_gasto', $idGasto)->first();
        if (!$g) return;

        $fec = $g->fecha_emision ? $g->fecha_emision.' 12:00:00' : now()->toDateTimeString();
        $total = (float)$g->neto + (float)$g->iva;

        // Debe: Gastos (5101) por neto
        if ($g->neto > 0) self::mov($fec, '5101-Gastos', 'Gasto '.$idGasto, (float)$g->neto, 0, 'gasto', $idGasto, $g->id_condominio);
        // Debe: IVA CrÃ©dito (1191) por iva
        if ($g->iva > 0)  self::mov($fec, '1191-IVA CrÃ©dito', 'Gasto '.$idGasto, (float)$g->iva, 0, 'gasto', $idGasto, $g->id_condominio);
        // Haber: Proveedores por pagar (2101) por total
        if ($total > 0)   self::mov($fec, '2101-Proveedores', 'Gasto '.$idGasto, 0, $total, 'gasto', $idGasto, $g->id_condominio);
    }
}
