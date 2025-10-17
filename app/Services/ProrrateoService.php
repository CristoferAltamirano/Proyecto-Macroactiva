<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProrrateoService
{
    public static function poblarFactores(int $idProrrateo): void
    {
        $r = DB::table('prorrateo_regla')->where('id_prorrateo',$idProrrateo)->first();
        if(!$r) return;

        $unidades = DB::table('unidad as u')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('g.id_condominio',$r->id_condominio)
            ->select('u.id_unidad','u.coef_prop','u.metros2','u.id_unidad_tipo')->get();

        foreach ($unidades as $u) {
            $factor = 0.0;
            switch ($r->criterio) {
                case 'coef_prop':  $factor = (float)$u->coef_prop; break;
                case 'por_m2':     $factor = max(0,(float)$u->metros2); break;
                case 'igualitario':$factor = 1.0; break;
                case 'por_tipo':
                    $tipo = DB::table('cat_unidad_tipo')->where('id_unidad_tipo',$u->id_unidad_tipo)->value('codigo');
                    $factor = match($tipo){
                        'vivienda'       => (float)($r->peso_vivienda ?? 1),
                        'bodega'         => (float)($r->peso_bodega ?? 1),
                        'estacionamiento'=> (float)($r->peso_estacionamiento ?? 1),
                        default => 1.0
                    };
                    break;
                case 'monto_fijo': $factor = 1.0; break;
            }

            DB::table('prorrateo_factor_unidad')->updateOrInsert(
                ['id_prorrateo'=>$idProrrateo,'id_unidad'=>$u->id_unidad],
                ['factor'=>round($factor,6)]
            );
        }
    }

    public static function generarCargos(int $idProrrateo, string $periodo): int
    {
        $r = DB::table('prorrateo_regla')->where('id_prorrateo',$idProrrateo)->first();
        if(!$r) return 0;

        self::poblarFactores($idProrrateo);

        $factors = DB::table('prorrateo_factor_unidad as f')
            ->join('unidad as u','u.id_unidad','=','f.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('f.id_prorrateo',$idProrrateo)
            ->where('g.id_condominio',$r->id_condominio)
            ->select('f.id_unidad','f.factor','u.id_unidad_tipo')->get();

        if ($factors->isEmpty()) return 0;

        $cuIds = DB::table('cargo_unidad as cu')
            ->where('periodo',$periodo)->where('id_concepto_cargo',$r->id_concepto_cargo)
            ->where('tipo', $r->tipo==='ordinario' ? 'normal' : 'extra')
            ->pluck('id_cargo_uni');
        if ($cuIds->isNotEmpty()) DB::table('cargo_unidad')->whereIn('id_cargo_uni',$cuIds)->delete();

        $creados=0;
        if ($r->criterio==='monto_fijo') {
            foreach ($factors as $f) {
                $tipo = DB::table('cat_unidad_tipo')->where('id_unidad_tipo',$f->id_unidad_tipo)->value('codigo');
                $monto = match($tipo){
                    'vivienda'       => (float)($r->peso_vivienda ?? $r->monto_total),
                    'bodega'         => (float)($r->peso_bodega ?? $r->monto_total),
                    'estacionamiento'=> (float)($r->peso_estacionamiento ?? $r->monto_total),
                    default => (float)$r->monto_total
                };
                if ($monto<=0) continue;
                DB::table('cargo_unidad')->insert([
                    'id_unidad'=>$f->id_unidad,'periodo'=>$periodo,
                    'id_concepto_cargo'=>$r->id_concepto_cargo,
                    'tipo'=> $r->tipo==='ordinario' ? 'normal' : 'extra',
                    'monto'=>$monto,'detalle'=>$r->descripcion ?? "Prorrateo {$r->criterio}"
                ]);
                $creados++;
            }
            return $creados;
        }

        $sum = (float) $factors->sum('factor');
        if ($sum<=0) return 0;

        $total = (float)($r->monto_total ?? 0);
        foreach ($factors as $f) {
            $monto = round($total * ($f->factor / $sum), 2);
            if ($monto<=0) continue;
            DB::table('cargo_unidad')->insert([
                'id_unidad'=>$f->id_unidad,'periodo'=>$periodo,
                'id_concepto_cargo'=>$r->id_concepto_cargo,
                'tipo'=> $r->tipo==='ordinario' ? 'normal' : 'extra',
                'monto'=>$monto,'detalle'=>$r->descripcion ?? "Prorrateo {$r->criterio}"
            ]);
            $creados++;
        }
        return $creados;
    }
}
