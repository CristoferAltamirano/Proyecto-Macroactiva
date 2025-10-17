<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CobroService
{
    /**
     * Genera/actualiza cobros del periodo desde cargos (cargo_unidad / cargo_individual),
     * recalcula totales, envía avisos y abona FR por periodo.
     */
    public static function generarDesdeCargos(string $periodo, ?int $idCondominio=null): int
    {
        // Unidades que tienen cargos para el periodo
        $q = DB::table('cargo_unidad as cu')
            ->join('unidad as u','u.id_unidad','=','cu.id_unidad')
            ->select('cu.id_unidad')
            ->where('cu.periodo',$periodo)
            ->groupBy('cu.id_unidad');

        if ($idCondominio) {
            $q->join('grupo as g','g.id_grupo','=','u.id_grupo')
              ->where('g.id_condominio',$idCondominio);
        }

        $unidades = $q->pluck('id_unidad');
        $estadoEmitido = IdCache::getId('cat_cobro_estado','emitido');

        $emitidos = 0;

        foreach ($unidades as $idUnidad) {
            // ¿Existe ya?
            $idCobro = DB::table('cobro')
                ->where(['id_unidad'=>$idUnidad,'periodo'=>$periodo,'tipo'=>'mensual'])
                ->value('id_cobro');

            if (!$idCobro) {
                $idCobro = DB::table('cobro')->insertGetId([
                    'id_unidad'=>$idUnidad,
                    'periodo'=>$periodo,
                    'emitido_at'=>now(),
                    'id_cobro_estado'=>$estadoEmitido,
                    'tipo'=>'mensual',
                    'total_cargos'=>0,'total_descuentos'=>0,'total_interes'=>0,'total_pagado'=>0,'saldo'=>0
                ]);
                $emitidos++;
            }

            // Limpia detalle (cargos/ajustes/descuentos previos del periodo)
            DB::table('cobro_detalle')
                ->where('id_cobro',$idCobro)
                ->whereIn('tipo',['cargo_comun','cargo_individual','ajuste','descuento'])
                ->delete();

            // Vuelve a poblar desde cargo_unidad
            $cargos = DB::table('cargo_unidad')
                ->where('id_unidad',$idUnidad)->where('periodo',$periodo)->get();

            foreach ($cargos as $c) {
                DB::table('cobro_detalle')->insert([
                    'id_cobro'=>$idCobro,
                    'tipo'=>'cargo_comun',
                    'id_cargo_uni'=>$c->id_cargo_uni,
                    'monto'=>$c->monto,
                    'glosa'=>$c->detalle
                ]);
            }

            // Cargos individuales del periodo
            $indv = DB::table('cargo_individual')
                ->where('id_unidad',$idUnidad)->where('periodo',$periodo)->get();

            foreach ($indv as $ci) {
                DB::table('cobro_detalle')->insert([
                    'id_cobro'=>$idCobro,
                    'tipo'=>'cargo_individual',
                    'id_cargo_indv'=>$ci->id_cargo_indv,
                    'monto'=>$ci->monto,
                    'glosa'=>$ci->detalle
                ]);
            }

            // Recalcula totales y estado
            self::recalcularTotales($idCobro);

            // Aviso por email
            \App\Services\EmailService::enviarAvisoCobro($idCobro);
        }

        // Abono Fondo de Reserva por condominio afectado (recargo del periodo)
        $condos = DB::table('cargo_unidad as cu')
            ->join('unidad as u','u.id_unidad','=','cu.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->where('cu.periodo',$periodo)
            ->when($idCondominio, fn($qq)=>$qq->where('g.id_condominio',$idCondominio))
            ->distinct()
            ->pluck('g.id_condominio');

        foreach ($condos as $cid) {
            FondoReservaService::abonarRecargoPeriodo($periodo, (int)$cid);
        }

        return $emitidos;
    }

    /**
     * Genera intereses de mora para cobros anteriores al periodo, con saldo > 0.
     * Usa la regla vigente del condominio a la fecha actual (hoy).
     */
    public static function generarIntereses(string $periodo, ?int $idCondominio=null): int
    {
        $hoy = now()->toDateString();

        $rows = DB::table('cobro as c')
            ->join('unidad as u','u.id_unidad','=','c.id_unidad')
            ->join('grupo as g','g.id_grupo','=','u.id_grupo')
            ->when($idCondominio, fn($q)=>$q->where('g.id_condominio',$idCondominio))
            ->where('c.periodo','<',$periodo)
            ->where('c.saldo','>',0)
            ->select('c.id_cobro','g.id_condominio')
            ->get();

        $n = 0;

        foreach ($rows as $r) {
            $regla = DB::table('interes_regla')
                ->where('id_condominio',$r->id_condominio)
                ->where('vigente_desde','<=',$hoy)
                ->where(fn($q)=>$q->whereNull('vigente_hasta')->orWhere('vigente_hasta','>=',$hoy))
                ->orderByDesc('vigente_desde')
                ->first();

            if (!$regla) continue;

            $c = DB::table('cobro')->where('id_cobro',$r->id_cobro)->first();
            if (!$c || $c->saldo<=0) continue;

            $int = round(($regla->tasa_anual_pct/100)/12 * (float)$c->saldo, 2);
            if ($int<=0) continue;

            // Deja 1 registro de interés por periodo (glosa = periodo)
            DB::table('cobro_detalle')
                ->where('id_cobro',$r->id_cobro)
                ->where('tipo','interes_mora')
                ->where('glosa',$periodo)
                ->delete();

            DB::table('cobro_detalle')->insert([
                'id_cobro'=>$r->id_cobro,
                'tipo'=>'interes_mora',
                'id_interes_regla'=>$regla->id_interes_regla,
                'tasa_aplicada_pct'=>$regla->tasa_anual_pct,
                'monto'=>$int,
                'glosa'=>$periodo
            ]);

            self::recalcularTotales($r->id_cobro);
            $n++;
        }

        return $n;
    }

    /**
     * Recalcula totales del cobro y setea el estado acorde al saldo/bruto.
     */
    public static function recalcularTotales(int $idCobro): void
    {
        $s = DB::table('cobro_detalle')
            ->selectRaw("
                SUM(CASE WHEN tipo IN ('cargo_comun','cargo_individual','ajuste') THEN monto ELSE 0 END) as cargos,
                SUM(CASE WHEN tipo='descuento' THEN monto ELSE 0 END) as descuentos,
                SUM(CASE WHEN tipo='interes_mora' THEN monto ELSE 0 END) as interes
            ")
            ->where('id_cobro',$idCobro)
            ->first();

        $pagado = (float) DB::table('pago_aplicacion')->where('id_cobro',$idCobro)->sum('monto_aplicado');

        $cargos = (float)($s->cargos ?? 0);
        $desc   = (float)($s->descuentos ?? 0);
        $int    = (float)($s->interes ?? 0);

        $bruto = $cargos + $int - $desc;
        $saldo = max(round($bruto - $pagado, 2), 0);

        DB::table('cobro')->where('id_cobro',$idCobro)->update([
            'total_cargos'=>$cargos,
            'total_descuentos'=>$desc,
            'total_interes'=>$int,
            'total_pagado'=>$pagado,
            'saldo'=>$saldo,
            'id_cobro_estado'=> self::estadoPorSaldo($saldo, $bruto),
        ]);
    }

    protected static function estadoPorSaldo(float $saldo, float $bruto): int
    {
        if ($bruto<=0)          return IdCache::getId('cat_cobro_estado','anulado');
        if ($saldo<=0.00001)    return IdCache::getId('cat_cobro_estado','pagado');
        if ($saldo<$bruto)      return IdCache::getId('cat_cobro_estado','parcial');
        return IdCache::getId('cat_cobro_estado','emitido');
    }
}
