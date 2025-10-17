<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnviarAvisos extends Command
{
    protected $signature = 'macro:avisos {periodo} {--condo=} {--todos}';
    protected $description = 'Envía avisos de cobro por email para un periodo';

    public function handle()
    {
        $periodo = $this->argument('periodo');
        $condo   = $this->option('condo') ? (int)$this->option('condo') : null;
        $soloPend = !$this->option('todos');

        $svc = new \App\Http\Controllers\AvisoCobroController();
        $req = new \Illuminate\Http\Request([
            'periodo'=>$periodo, 'id_condominio'=>$condo, 'solo_pendientes'=>$soloPend ? 1:0
        ]);
        $svc->enviarMasivo($req);
        $this->info("Avisos procesados para $periodo");
        return self::SUCCESS;
    }
}
