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
        Schema::create('cobros', function (Blueprint $table) {
            $table->bigIncrements('id_cobro');
            $table->unsignedBigInteger('id_unidad');
            $table->unsignedBigInteger('id_cobro_estado');
            $table->string('periodo');
            $table->integer('monto_fondo_reserva')->default(0);
            $table->integer('monto_total')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};