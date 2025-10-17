<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    /**
     * Genera un dump comprimido en storage/app/backups/YYYYMMDD/db-YYYYMMDD_HHMMSS.sql.gz
     * Requiere mysqldump disponible en el PATH o en DB_DUMP_BIN.
     */
    public static function dump(): ?string
    {
        @Storage::makeDirectory('backups/'.date('Ymd'));

        $fn = 'backups/'.date('Ymd').'/db-'.date('Ymd_His').'.sql.gz';
        $full = Storage::path($fn);

        $db = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $bin = env('DB_DUMP_BIN', 'mysqldump');

        // Seguridad: pasa password por env var para no exponerlo en listado de procesos
        $cmd = sprintf(
            'set MYSQL_PWD=%s && %s --single-transaction --hex-blob --routines --triggers --host=%s --port=%s -u%s %s | gzip > %s',
            escapeshellarg($pass),
            escapeshellcmd($bin),
            escapeshellarg($host),
            escapeshellarg((string)$port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($full)
        );

        try {
            $exit = null;
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows: usar cmd /C
                $exit = @pclose(@popen('cmd /C ' . $cmd, 'r'));
            } else {
                $exit = @pclose(@popen($cmd, 'r'));
            }

            if ($exit === 0 || $exit === null) {
                Log::info('[BackupService] Respaldo creado: '.$fn);
                return $fn;
            }
            Log::warning('[BackupService] mysqldump devolvió exit='.$exit);
        } catch (\Throwable $e) {
            Log::error('[BackupService] Error: '.$e->getMessage());
        }
        return null;
    }
}
