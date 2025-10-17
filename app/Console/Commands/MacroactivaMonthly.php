<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MacroactivaMonthly extends Command
{
    protected $signature = 'macroactiva:monthly {periodo?} {--condo=} {--skip-mail}';
    protected $description = 'Tareas mensuales: generar cobros, intereses, avisos y respaldo.';

    public function handle(): int
    {
        $periodo = $this->argument('periodo') ?? now()->format('Ym'); // AAAAMM actual
        $cid = $this->option('condo') ?: DB::table('condominio')->value('id_condominio');

        $this->info("Iniciando mensual periodo={$periodo} condominio={$cid}");

        try {
            // 1) Generar cobros desde cargos del periodo
            $emitidos = \App\Services\CobroService::generarDesdeCargos($periodo, (int)$cid);
            $this->info("Cobros emitidos/actualizados: $emitidos");

            // 2) Generar intereses a saldos anteriores
            $ints = \App\Services\CobroService::generarIntereses($periodo, (int)$cid);
            $this->info("Intereses generados: $ints");

            // 3) (Opcional) Enviar avisos de cobro
            if (!$this->option('skip-mail') && class_exists(\App\Http\Controllers\AvisoCobroController::class)) {
                $this->info("Enviando avisos de cobro...");
                $ctrl = app(\App\Http\Controllers\AvisoCobroController::class);
                $req = \Illuminate\Http\Request::create('/admin/avisos/enviar', 'POST', ['periodo'=>$periodo]);
                $ctrl->enviar($req); // Se asume que el método existe
            } else {
                $this->info("Avisos: omitido (--skip-mail o controlador no disponible).");
            }

            // 4) Respaldo DB
            $this->info("Generando respaldo...");
            $path = \App\Services\BackupService::dump();
            $this->info($path ? "Respaldo OK: storage/app/$path" : "Respaldo falló (revisar logs)");

            $this->info("Tareas mensuales completadas.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[macroactiva:monthly] '.$e->getMessage());
            $this->error('Error: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
