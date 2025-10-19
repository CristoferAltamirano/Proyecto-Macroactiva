<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('conciliacion')) {
            Schema::create('conciliacion', function (Blueprint $t) {
                $t->bigIncrements('id_conciliacion');
                $t->unsignedBigInteger('id_condominio');
                $t->string('archivo_nombre',255);
                $t->date('periodo_desde')->nullable();
                $t->date('periodo_hasta')->nullable();
                $t->integer('total_registros')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (!Schema::hasTable('conciliacion_item')) {
            Schema::create('conciliacion_item', function (Blueprint $t) {
                $t->bigIncrements('id_item');
                $t->unsignedBigInteger('id_conciliacion');
                $t->date('fecha');
                $t->string('glosa',200)->nullable();
                $t->decimal('monto',12,2);
                $t->enum('estado',['pendiente','aplicado','creado'])->default('pendiente');
                $t->unsignedBigInteger('id_pago')->nullable();
                $t->text('sugerencias_json')->nullable();
                $t->string('nota',300)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('updated_at')->nullable();

                $t->index(['id_conciliacion']);
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('conciliacion_item');
        Schema::dropIfExists('conciliacion');
    }
};
