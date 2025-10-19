<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cierre_anual')) {
            Schema::create('cierre_anual', function (Blueprint $t) {
                $t->bigIncrements('id_cierre_anual');
                $t->unsignedBigInteger('id_condominio');
                $t->integer('anio');
                $t->unsignedBigInteger('cerrado_por')->nullable();
                $t->timestamp('cerrado_at')->nullable();
                $t->unique(['id_condominio','anio'],'uk_cierre_anio');
                $t->index(['id_condominio','anio']);
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('cierre_anual');
    }
};
