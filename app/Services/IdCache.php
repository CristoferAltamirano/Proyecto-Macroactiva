<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IdCache
{
    /** Mapa tabla => PK */
    protected static array $pkMap = [
        'cat_cobro_estado' => 'id_cobro_estado',
        'cat_metodo_pago'  => 'id_metodo_pago',
        'cat_pasarela'     => 'id_pasarela',
        'cat_estado_tx'    => 'id_estado_tx',
    ];

    public static function getId(string $table, string $codigo): int
    {
        $pk = self::$pkMap[$table] ?? 'id';
        return Cache::remember("idcache:$table:$codigo", 3600, function() use ($table,$codigo,$pk) {
            return (int) DB::table($table)->where('codigo',$codigo)->value($pk);
        });
    }
}
