<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanCuentasSeeder extends Seeder
{
    public function run(): void
    {
        $base = [
            // Activo
            ['codigo'=>'1101','nombre'=>'Caja'],
            ['codigo'=>'1102','nombre'=>'Bancos cuenta corriente'],
            ['codigo'=>'1201','nombre'=>'Deudores varios'],
            // Pasivo
            ['codigo'=>'2101','nombre'=>'Proveedores por pagar'],
            ['codigo'=>'2201','nombre'=>'Anticipos de clientes'],
            // Patrimonio
            ['codigo'=>'3101','nombre'=>'Patrimonio'],
            ['codigo'=>'3201','nombre'=>'Resultados acumulados'], // requerida por cierre anual
            // Ingresos (4xxx)
            ['codigo'=>'4101','nombre'=>'Ingresos gastos comunes'],
            ['codigo'=>'4102','nombre'=>'Ingresos extraordinarios'],
            // Gastos (5xxx)
            ['codigo'=>'5101','nombre'=>'Gasto conserjería/seguridad'],
            ['codigo'=>'5102','nombre'=>'Servicios básicos'],
            ['codigo'=>'5103','nombre'=>'Mantenciones'],
            ['codigo'=>'5104','nombre'=>'Remuneraciones/Honorarios'],
            ['codigo'=>'5105','nombre'=>'Proveedores varios'],
        ];

        foreach ($base as $c) {
            $ex = DB::table('cuenta_contable')->where('codigo',$c['codigo'])->first();
            if (!$ex) DB::table('cuenta_contable')->insert($c);
        }
    }
}
