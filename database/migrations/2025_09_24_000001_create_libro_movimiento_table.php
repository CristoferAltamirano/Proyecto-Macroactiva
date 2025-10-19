<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('libro_movimiento', function (Blueprint $table) {
            $table->bigIncrements('id_libro_mov');
            $table->unsignedBigInteger('id_condominio');
            $table->unsignedBigInteger('id_cta_contable');
            $table->string('glosa');
            $table->integer('debe')->default(0);
            $table->integer('haber')->default(0);
            $table->timestamp('fecha')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libro_movimiento');
    }
};