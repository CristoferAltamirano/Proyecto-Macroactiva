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
    Schema::create('cobros', function (Blueprint $table) {
        $table->id();
        $table->foreignId('unidad_id')->constrained('unidades')->onDelete('cascade');
        $table->date('periodo'); // Primer día del mes del cobro. Ej: 2025-10-01
        $table->unsignedInteger('monto_gasto_comun');
        $table->unsignedInteger('monto_fondo_reserva');
        $table->unsignedInteger('monto_multas')->default(0);
        $table->unsignedInteger('monto_total');
        $table->enum('estado', ['pendiente', 'pagado'])->default('pendiente');
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
