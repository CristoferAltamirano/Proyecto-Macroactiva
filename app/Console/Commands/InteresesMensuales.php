<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InteresesMensuales extends Command
{
    protected $signature = 'macro:intereses {periodo?} {--condo=}';
    protected $description = 'Genera intereses por mora para saldos anteriores al periodo dado (default: actual)';

    public function handle()
    {
        $periodo = $this->argument('periodo') ?? now()->format('Ym');
        $condo = $this->option('condo') ? (int)$this->option('condo') : null;

        $n = \App\Services\CobroService::generarIntereses($periodo, $condo);
        $this->info("Intereses generados: $n");
        return self::SUCCESS;
    }
}
