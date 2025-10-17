<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditoriaService
{
    /**
     * Obtiene el largo máximo (CHARACTER_MAXIMUM_LENGTH) de una columna si aplica.
     * Solo intenta para MySQL/MariaDB; en otros drivers retorna null.
     */
    protected static function getColumnMaxLength(string $table, string $column): ?int
    {
        try {
            $cn     = DB::connection();
            $driver = $cn->getDriverName();

            // Solo MySQL/MariaDB tienen information_schema como lo usamos acá
            if (!in_array($driver, ['mysql', 'mariadb'], true)) {
                return null;
            }

            $schema   = $cn->getDatabaseName();
            $prefix   = DB::getTablePrefix();
            $tblName  = $prefix ? ($prefix . $table) : $table;

            $row = DB::selectOne(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS L
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME   = ?
                    AND COLUMN_NAME  = ?
                  LIMIT 1",
                [$schema, $tblName, $column]
            );

            if ($row && isset($row->L) && $row->L !== null) {
                $len = (int) $row->L;
                return $len > 0 ? $len : null;
            }
        } catch (\Throwable $e) {
            // Silencioso: driver no soportado o sin permisos; seguimos con fallbacks
        }
        return null;
    }

    /**
     * Trunca un valor al tamaño máximo de la columna si es necesario.
     * Si no podemos obtener el tamaño, y la columna es "string" según Schema, usa fallback 255.
     */
    protected static function fitToColumn(string $table, string $column, string $value): string
    {
        $max = self::getColumnMaxLength($table, $column);

        if ($max === null) {
            try {
                $colType = Schema::getColumnType($table, $column); // puede lanzar en algunos drivers
                if ($colType === 'string') {
                    $max = 255; // fallback sensato para VARCHAR
                }
            } catch (\Throwable $e) {
                // sin info: no recortamos
            }
        }

        if ($max !== null && mb_strlen($value, 'UTF-8') > $max) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }
        return $value;
    }

    /**
     * Registra un evento de auditoría de forma tolerante a esquemas distintos.
     *
     * @param string            $entidad     Ej: 'pago', 'trabajador'
     * @param int|string|null   $entidadId   ID de la entidad (0 si no aplica)
     * @param string            $accion      Ej: CREAR, GUARDAR, ELIMINAR, ERROR_CREAR, etc.
     * @param mixed             $payload     Array/stdClass/string con info adicional (se guarda como JSON si hay columna compatible)
     */
    public static function log(string $entidad, $entidadId, string $accion, $payload = null): void
    {
        try {
            if (!Schema::hasTable('auditoria')) {
                return; // sin tabla, no hacemos nada
            }

            // Usuario actual (id flexible)
            $uid = null;
            if (Auth::check()) {
                $u = Auth::user();
                $uid = $u->id_usuario ?? $u->id ?? null;
            }

            $row = [];

            // Campos base
            if (Schema::hasColumn('auditoria', 'entidad')) {
                $row['entidad'] = $entidad;
            }
            if (Schema::hasColumn('auditoria', 'entidad_id')) {
                $row['entidad_id'] = (int) ($entidadId ?? 0);
            }

            // Acción: recortar si la columna es corta
            if (Schema::hasColumn('auditoria', 'accion')) {
                $row['accion'] = self::fitToColumn('auditoria', 'accion', (string)$accion);
            }

            // Usuario (columnas alternativas)
            if (Schema::hasColumn('auditoria', 'id_usuario')) {
                $row['id_usuario'] = $uid;
            } elseif (Schema::hasColumn('auditoria', 'user_id')) {
                $row['user_id'] = $uid;
            } elseif (Schema::hasColumn('auditoria', 'usuario')) {
                $row['usuario'] = $uid;
            }

            // IP / User-Agent si existen
            $ip = request()->ip() ?? null;
            $ua = request()->userAgent() ?? null;
            if (Schema::hasColumn('auditoria', 'ip'))         $row['ip'] = $ip;
            if (Schema::hasColumn('auditoria', 'user_agent')) $row['user_agent'] = $ua;

            // Payload serializado
            $json = null;
            if (!is_null($payload)) {
                $json = is_string($payload)
                    ? $payload
                    : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            foreach (['detalle_json','payload','datos','meta','detalle','data','observacion'] as $col) {
                if (Schema::hasColumn('auditoria', $col) && $json !== null) {
                    $row[$col] = self::fitToColumn('auditoria', $col, $json);
                    break;
                }
            }

            // Timestamps si existen
            foreach (['created_at','fecha'] as $col) {
                if (Schema::hasColumn('auditoria', $col) && !isset($row[$col])) {
                    $row[$col] = now();
                }
            }

            // Fallback de entidad_id por si la columna es NOT NULL y no la llenamos arriba
            if (Schema::hasColumn('auditoria', 'entidad_id') && !array_key_exists('entidad_id', $row)) {
                $row['entidad_id'] = (int) ($entidadId ?? 0);
            }

            DB::table('auditoria')->insert($row);
        } catch (\Throwable $e) {
            // Nunca romper el flujo por auditoría
            Log::warning('AUDITORIA log falló (ignorado)', ['e' => $e->getMessage()]);
        }
    }
}
