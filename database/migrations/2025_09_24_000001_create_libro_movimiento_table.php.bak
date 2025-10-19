<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('libro_movimiento')) {
            Schema::create('libro_movimiento', function (Blueprint $table) {
                $table->bigIncrements('id_mov');
                $table->unsignedBigInteger('id_condominio')->nullable()->index();
                $table->date('fecha')->index();
                $table->unsignedBigInteger('id_cta_contable')->index(); // <- numérica (coincide con triggers)
                $table->decimal('debe', 14, 2)->default(0);
                $table->decimal('haber', 14, 2)->default(0);
                $table->string('ref_tabla', 40)->nullable(); // 'pago','gasto','remuneracion', etc.
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->string('glosa', 300)->nullable();
                $table->timestamps();

                $table->index(['ref_tabla','ref_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('libro_movimiento');
    }
};
