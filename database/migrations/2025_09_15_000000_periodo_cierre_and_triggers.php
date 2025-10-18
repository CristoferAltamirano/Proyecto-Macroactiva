<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('periodo_cierre', function(Blueprint $t){
            $t->id('id_cierre')->startingValue(1);
            $t->unsignedBigInteger('id_condominio');
            $t->char('periodo',6); // AAAAMM
            $t->unsignedBigInteger('cerrado_por')->nullable();
            $t->timestamp('cerrado_at')->useCurrent();
            $t->unique(['id_condominio','periodo'], 'uk_cierre_condo_periodo');
            $t->index(['periodo']);
        });

        // Utilidad SQL para obtener condominio desde unidad
        DB::unprepared("DROP VIEW IF EXISTS vw_unidad_condo;");
        DB::unprepared("
        CREATE VIEW vw_unidad_condo AS
        SELECT u.id_unidad, g.id_condominio
        FROM unidad u
        LEFT JOIN grupo g ON g.id_grupo = u.id_grupo
        ");
    }

    public function down(): void
    {
        // Eliminar triggers
        foreach ([
            'trg_pago_bi_cierre','trg_pago_bu_cierre',
            'trg_cu_bi_cierre','trg_cu_bu_cierre',
            'trg_ci_bi_cierre','trg_ci_bu_cierre',
            'trg_cobro_bi_cierre','trg_cobro_bu_cierre',
            'trg_gasto_bi_cierre','trg_gasto_bu_cierre',
            'trg_remu_bi_cierre','trg_remu_bu_cierre',
        ] as $trg) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trg};");
        }
        DB::statement("DROP VIEW IF EXISTS vw_unidad_condo");
        Schema::dropIfExists('periodo_cierre');
    }
};
