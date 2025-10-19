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
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::create('pagos', function (Blueprint $table) use ($isSqlite) {
            $table->bigIncrements('id_pago');
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('id_unidad')->nullable();
            $table->integer('monto_pagado')->default(0);
            $table->timestamp('fecha_pago')->nullable();
            $table->string('metodo_pago')->nullable();
            $table->string('webpay_token')->nullable();
            $table->timestamps();

            if (! $isSqlite) {
                $table->foreign('cobro_id')->references('id_cobro')->on('cobros')->cascadeOnDelete();
                $table->foreign('id_unidad')->references('id_unidad')->on('unidades');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};