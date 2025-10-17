<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Listado de tablas donde suele pegar el trigger/log
        $tables = [
            'libro_movimiento',   // asientos contables
            'auditoria',          // bitácora si la usas por trigger
            'pago',               // por si algún trigger usa NEW.created_at
            'comprobante_pago',   // bitácora de comprobantes
        ];

        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) continue;

            Schema::table($t, function (Blueprint $table) use ($t) {
                if (!Schema::hasColumn($t, 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn($t, 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'libro_movimiento',
            'auditoria',
            'pago',
            'comprobante_pago',
        ];

        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) continue;

            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn($t, 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }
};
