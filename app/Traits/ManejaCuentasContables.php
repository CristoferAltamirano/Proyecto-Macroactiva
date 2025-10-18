<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ManejaCuentasContables
{
    // Definimos los códigos de las cuentas que el sistema necesita para operar.
    // Estos códigos deben existir en la tabla `cuenta_contable`.
    protected $codigosDeCuentas = [
        'caja_banco' => '1.1.01',
        'cuentas_por_cobrar' => '1.1.02',
        'cuentas_por_pagar' => '2.1.01',
        'ingresos_gasto_comun' => '4.1.01',
        'egresos_general' => '5.1.01',
    ];

    /**
     * Obtiene el ID de una cuenta contable a partir de su clave funcional.
     * Usa caché para evitar consultas repetidas a la base de datos.
     *
     * @param string $clave La clave funcional de la cuenta (ej. 'caja_banco').
     * @return int|null
     */
    protected function obtenerIdCuenta(string $clave): ?int
    {
        if (!isset($this->codigosDeCuentas[$clave])) {
            return null;
        }

        $codigo = $this->codigosDeCuentas[$clave];
        $cacheKey = 'cuenta_id_' . $codigo;

        // Cache por 60 minutos para optimizar.
        return Cache::remember($cacheKey, 3600, function () use ($codigo) {
            return DB::table('cuenta_contable')->where('codigo', $codigo)->value('id_cta_contable');
        });
    }
}