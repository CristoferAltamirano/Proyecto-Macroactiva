<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CuentasSeeder extends Seeder
{
    public function run(): void
    {
        // Mapa código => nombre (plan básico, compatible con tu Ledger)
        $cuentas = [
            // Activo
            '1101' => 'Banco cuenta corriente',
            '1102' => 'Caja',
            '1201' => 'Deudores por gastos comunes',
            '1202' => 'Documentos por cobrar',
            '1301' => 'Anticipos de copropietarios',
            // Pasivo
            '2101' => 'Proveedores por pagar',
            '2102' => 'Remuneraciones por pagar',
            '2201' => 'Impuestos por pagar',
            // Patrimonio / Fondos
            '3101' => 'Fondo de reserva',
            '3201' => 'Resultados acumulados',
            // Ingresos
            '4001' => 'Ingresos por gastos comunes',
            '4002' => 'Ingresos extraordinarios',
            '4101' => 'Intereses por mora',
            '4201' => 'Descuentos otorgados',
            // Gastos
            '5001' => 'Gastos comunes (mantención)',
            '5002' => 'Servicios básicos',
            '5101' => 'Remuneraciones personal',
            '5102' => 'Honorarios/Contratas',
            '5201' => 'Gastos proveedores varios',
            '5301' => 'Gastos bancarios/comisiones',
        ];

        DB::beginTransaction();
        try {
            foreach ($cuentas as $codigo => $nombre) {
                $exists = DB::table('cuenta_contable')->where('codigo', $codigo)->exists();
                if (!$exists) {
                    DB::table('cuenta_contable')->insert([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                    ]);
                } else {
                    DB::table('cuenta_contable')->where('codigo',$codigo)->update(['nombre'=>$nombre]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
