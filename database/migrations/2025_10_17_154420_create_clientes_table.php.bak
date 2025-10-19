<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('clientes', function (Blueprint $table) {
        $table->id(); // ID numérico automático (1, 2, 3...)
        $table->string('nombre');
        $table->string('email')->unique(); // El email debe ser único
        $table->string('telefono')->nullable(); // nullable significa que puede estar vacío
        $table->string('estado'); // Ej: "Activo", "Inactivo"
        $table->timestamps(); // Columnas created_at y updated_at automáticas
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
