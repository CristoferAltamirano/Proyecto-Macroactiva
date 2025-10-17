<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditService
{
    public static function log(string $entidad, int $entidadId, string $accion, array $detalle = []): void
    {
        $u = auth()->user();
        DB::table('auditoria')->insert([
            'entidad'       => $entidad,
            'entidad_id'    => $entidadId,
            'accion'        => strtoupper($accion),
            'id_usuario'    => $u->id_usuario ?? null,
            'usuario_email' => $u->email ?? null,
            'detalle'       => json_encode($detalle, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'created_at'    => now(),
        ]);
    }
}
