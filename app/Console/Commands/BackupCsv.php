<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupCsv extends Command
{
    protected $signature = 'macro:backup {--path=backups}';
    protected $description = 'Genera CSVs de respaldo (pagos y cobros)';

    public function handle()
    {
        $path = $this->option('path');
        $ts = now()->format('Ymd_His');

        // Pagos
        $pagos = DB::table('pago')->orderBy('id_pago')->get();
        $csv1 = $this->toCsv([array_keys((array)$pagos->first() ?? [])], $pagos->map(fn($r)=>(array)$r)->all());
        Storage::put("$path/pagos_$ts.csv", "\xEF\xBB\xBF".$csv1);

        // Cobros
        $cobros = DB::table('cobro')->orderBy('id_cobro')->get();
        $csv2 = $this->toCsv([array_keys((array)$cobros->first() ?? [])], $cobros->map(fn($r)=>(array)$r)->all());
        Storage::put("$path/cobros_$ts.csv", "\xEF\xBB\xBF".$csv2);

        $this->info("Backups guardados en storage/app/$path");
        return self::SUCCESS;
    }

    private function toCsv(array $headers, array $rows): string
    {
        $f = fopen('php://temp','r+');
        foreach ($headers as $h) fputcsv($f,$h);
        foreach ($rows as $r) fputcsv($f,$r);
        rewind($f); return stream_get_contents($f);
    }
}
