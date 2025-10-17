<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CierreAnualService
{
    public static function cerrar(int $anio, int $idCondominio, int $userId): void
    {
        if ($anio < 2000 || $anio > 2100) throw new RuntimeException('Año inválido');
        DB::beginTransaction();
        try {
            // Ya cerrado?
            $exists = DB::table('cierre_anual')->where(['id_condominio'=>$idCondominio,'anio'=>$anio])->exists();
            if ($exists) throw new RuntimeException("El año $anio ya está cerrado.");

            $fecha = sprintf('%d-12-31', $anio);

            // Saldos por cuenta en el año
            $saldos = DB::table('libro_movimiento as l')
                ->join('cuenta_contable as cc','cc.id_cta_contable','=','l.id_cta_contable')
                ->selectRaw('cc.id_cta_contable, cc.codigo, cc.nombre, ROUND(SUM(l.debe - l.haber),2) as saldo')
                ->where('l.id_condominio',$idCondominio)
                ->whereYear('l.fecha',$anio)
                ->where(function($q){
                    $q->where('cc.codigo','like','4%')->orWhere('cc.codigo','like','5%');
                })
                ->groupBy('cc.id_cta_contable','cc.codigo','cc.nombre')
                ->orderBy('cc.codigo')
                ->get();

            if ($saldos->isEmpty()) {
                // Aun así podemos cerrar (quedará sin asientos de cierre)
            }

            $sumDebe=0; $sumHaber=0;
            foreach ($saldos as $s) {
                $neto = (float)$s->saldo; // >0 = saldo deudor; <0 = saldo acreedor
                if (abs($neto) < 0.005) continue;

                // Para dejar la cuenta en cero, hacemos el asiento inverso:
                $debe = 0.00; $haber = 0.00;
                if ($neto > 0) { // deudor -> ponemos haber
                    $haber = abs($neto);
                } else { // acreedor -> ponemos debe
                    $debe  = abs($neto);
                }

                DB::table('libro_movimiento')->insert([
                    'id_condominio'=>$idCondominio,
                    'fecha'=>$fecha,
                    'id_cta_contable'=>$s->id_cta_contable,
                    'debe'=>$debe,
                    'haber'=>$haber,
                    'ref_tabla'=>'CIERRE_ANUAL',
                    'ref_id'=>$anio,
                    'glosa'=>"Cierre anual $anio - Cta {$s->codigo}",
                ]);

                $sumDebe  += $debe;
                $sumHaber += $haber;
            }

            // Contrapartida a 3201 Resultados acumulados
            $cta3201 = DB::table('cuenta_contable')->where('codigo','3201')->value('id_cta_contable');
            if (!$cta3201) {
                $cta3201 = DB::table('cuenta_contable')->insertGetId(['codigo'=>'3201','nombre'=>'Resultados acumulados']);
            }

            if (abs($sumDebe - $sumHaber) >= 0.005) {
                $diff = round(abs($sumDebe - $sumHaber),2);
                if ($sumDebe > $sumHaber) {
                    // Resultado POSITIVO -> CREDITAMOS 3201
                    DB::table('libro_movimiento')->insert([
                        'id_condominio'=>$idCondominio,
                        'fecha'=>$fecha,
                        'id_cta_contable'=>$cta3201,
                        'debe'=>0.00,
                        'haber'=>$diff,
                        'ref_tabla'=>'CIERRE_ANUAL',
                        'ref_id'=>$anio,
                        'glosa'=>"Resultado del ejercicio $anio",
                    ]);
                    $sumHaber += $diff;
                } else {
                    // Resultado NEGATIVO -> DEBITAMOS 3201
                    DB::table('libro_movimiento')->insert([
                        'id_condominio'=>$idCondominio,
                        'fecha'=>$fecha,
                        'id_cta_contable'=>$cta3201,
                        'debe'=>$diff,
                        'haber'=>0.00,
                        'ref_tabla'=>'CIERRE_ANUAL',
                        'ref_id'=>$anio,
                        'glosa'=>"Pérdida del ejercicio $anio",
                    ]);
                    $sumDebe += $diff;
                }
            }

            // Marca cierre
            DB::table('cierre_anual')->insert([
                'id_condominio'=>$idCondominio,
                'anio'=>$anio,
                'cerrado_por'=>$userId,
                'cerrado_at'=>now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function reabrir(int $anio, int $idCondominio): void
    {
        DB::beginTransaction();
        try {
            DB::table('libro_movimiento')
                ->where(['id_condominio'=>$idCondominio,'ref_tabla'=>'CIERRE_ANUAL','ref_id'=>$anio])
                ->delete();
            DB::table('cierre_anual')->where(['id_condominio'=>$idCondominio,'anio'=>$anio])->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
