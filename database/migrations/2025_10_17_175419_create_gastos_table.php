<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->bigIncrements('id_gasto');
            $table->unsignedBigInteger('condominio_id');
            $table->string('periodo');
            $table->unsignedBigInteger('id_gasto_categ');
            $table->integer('neto')->default(0);
            $table->integer('iva')->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_emision')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};