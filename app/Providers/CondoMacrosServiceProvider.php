<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class CondoMacrosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /**
         * Macro forCondo: aplica un where('id_condominio', $ctx) si hay contexto.
         * Si no hay contexto en sesión, NO modifica la query.
         */
        DB::macro('forCondo', function (?int $ctxId = null) {
            $ctx = $ctxId ?? session('ctx_condo_id');

            /** @var \Illuminate\Database\Query\Builder $builder */
            $builder = DB::query();

            $builder->when($ctx, function ($q) use ($ctx) {
                $q->where('id_condominio', $ctx);
            });

            return $builder;
        });
    }
}
