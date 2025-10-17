<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibroService
{
    /**
     * Inserta el asiento doble en libro_movimiento usando códigos contables.
     * - Evita duplicados: si ya existen asientos para (ref_tabla, ref_id) no vuelve a insertar.
     * - Alineado a tu modelo actual (columna id_cta_contable).
     */
    public static function asiento(
        int $idCondominio,
        string $fecha,                 // 'Y-m-d' preferible
        string $ctaDebeCodigo,         // p.ej. '1101'
        string $ctaHaberCodigo,        // p.ej. '1201'
        float $monto,
        ?string $refTabla,
        ?int $refId,
        string $glosa = ''
    ): void
    {
        // Si no existe la tabla o la columna esperada, no hacemos nada
        if (!Schema::hasTable('libro_movimiento') || !Schema::hasColumn('libro_movimiento','id_cta_contable')) {
            return;
        }

        // Evitar duplicar asientos si ya hay alguno para esa referencia
        if ($refTabla && $refId) {
            $ya = DB::table('libro_movimiento')
                ->where('ref_tabla', $refTabla)
                ->where('ref_id', $refId)
                ->exists();
            if ($ya) return;
        }

        // Buscar ids de cuentas
        $idDebe  = (int) DB::table('cuenta_contable')->where('codigo', $ctaDebeCodigo)->value('id_cta_contable');
        $idHaber = (int) DB::table('cuenta_contable')->where('codigo', $ctaHaberCodigo)->value('id_cta_contable');

        if (!$idDebe || !$idHaber || $monto <= 0) return;

        // Normaliza fecha a 'Y-m-d'
        try {
            $fechaSQL = date('Y-m-d', strtotime($fecha));
        } catch (\Throwable $e) {
            $fechaSQL = date('Y-m-d');
        }

        DB::transaction(function () use ($idCondominio, $fechaSQL, $idDebe, $idHaber, $monto, $refTabla, $refId, $glosa) {
            DB::table('libro_movimiento')->insert([
                'id_condominio'  => $idCondominio,
                'fecha'          => $fechaSQL,
                'id_cta_contable'=> $idDebe,
                'debe'           => $monto,
                'haber'          => 0,
                'ref_tabla'      => $refTabla,
                'ref_id'         => $refId,
                'glosa'          => mb_substr((string)$glosa, 0, 300),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::table('libro_movimiento')->insert([
                'id_condominio'  => $idCondominio,
                'fecha'          => $fechaSQL,
                'id_cta_contable'=> $idHaber,
                'debe'           => 0,
                'haber'          => $monto,
                'ref_tabla'      => $refTabla,
                'ref_id'         => $refId,
                'glosa'          => mb_substr((string)$glosa, 0, 300),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });
    }

    /**
     * Asiento por PAGO:
     *  Debe  = 1101 Banco (o 1102 Caja según método)
     *  Haber = 1201 Cuentas por cobrar
     * Nota: Evita duplicados si triggers ya generaron el asiento.
     */
    public static function asientoPago(int $idPago): void
    {
        $p = DB::table('pago')->where('id_pago', $idPago)->first();
        if (!$p) return;

        $condo = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('u.id_unidad', $p->id_unidad)
            ->value('g.id_condominio');

        if (!$condo) return;

        // Método de pago -> cuenta de banco/caja
        $met = DB::table('cat_metodo_pago')->where('id_metodo_pago', $p->id_metodo_pago)->value('codigo') ?? 'transferencia';
        // Mapea a banco/caja: transferencia/webpay/tarjeta => banco (1101), si no => caja (1102)
        $ctaBancoOCaja = in_array($met, ['transferencia','webpay','tarjeta']) ? '1101' : '1102';

        // Pago contra CxC (NO contra ingresos: ingresos se reconocen al emitir el cobro)
        $ctaCxC = '1201';

        $fecha = is_string($p->fecha_pago) ? $p->fecha_pago : (string)$p->fecha_pago;
        $monto = (float)$p->monto;

        self::asiento((int)$condo, $fecha, $ctaBancoOCaja, $ctaCxC, $monto, 'pago', (int)$p->id_pago, 'Cobranza GC');
    }

    /**
     * Asiento por GASTO (devengo):
     *  Debe  = 5101 Gasto (neto)
     *  Debe  = 1191 IVA Crédito Fiscal (iva)
     *  Haber = 2101 Proveedores (neto + iva)
     * Nota: Evita duplicados si triggers ya generaron el asiento.
     */
    public static function asientoGasto(int $idGasto): void
    {
        $g = DB::table('gasto')->where('id_gasto', $idGasto)->first();
        if (!$g) return;

        $neto = (float)($g->neto ?? 0);
        $iva  = (float)($g->iva  ?? 0);
        $total = $neto + $iva;
        if ($total <= 0) return;

        $fecha = $g->fecha_emision ? (is_string($g->fecha_emision) ? $g->fecha_emision : (string)$g->fecha_emision) : now()->toDateString();

        // 5101 (Gasto) vs 2101 (Proveedores) por el neto
        self::asiento(
            (int)$g->id_condominio,
            $fecha,
            '5101',
            '2101',
            $neto,
            'gasto',
            (int)$g->id_gasto,
            'Devengo gasto'
        );

        // 1191 (IVA CF) vs 2101 (Proveedores) por el IVA
        if ($iva > 0) {
            self::asiento(
                (int)$g->id_condominio,
                $fecha,
                '1191',
                '2101',
                $iva,
                'gasto',
                (int)$g->id_gasto,
                'IVA Crédito Fiscal'
            );
        }
    }
}
