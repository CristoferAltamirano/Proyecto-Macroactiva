<?php

namespace App\Console\Commands; 

use Illuminate\Console\Command;

class DemoDataCommand extends Command
{
    protected $signature = 'demo:seed';
    protected $description = 'Puebla la base de datos con datos de demostración (condominio, unidades, usuarios, cargos, cobros, pagos, gastos)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        $this->info('Datos de demo listos ✔');
        return self::SUCCESS;
    }
}
