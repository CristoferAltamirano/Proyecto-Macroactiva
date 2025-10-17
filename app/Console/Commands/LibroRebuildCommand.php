<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LibroRebuildCommand extends Command
{
    protected $signature = 'libro:rebuild';
    protected $description = 'Reconstruye el libro mayor desde pagos, gastos y cobros.';

    public function handle(): int
    {
        DB::table('libro_movimiento')->truncate();

        $this->info('Cobros...');
        foreach (DB::table('cobro')->pluck('id_cobro') as $id) \App\Services\LedgerService::asientoCobro((int)$id);

        $this->info('Pagos...');
        foreach (DB::table('pago')->pluck('id_pago') as $id) \App\Services\LedgerService::asientoPago((int)$id);

        $this->info('Gastos...');
        foreach (DB::table('gasto')->pluck('id_gasto') as $id) \App\Services\LedgerService::asientoGasto((int)$id);

        $this->info('Listo.');
        return self::SUCCESS;
    }
}
