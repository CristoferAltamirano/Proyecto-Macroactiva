<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renombramos la tabla
        Schema::rename('clientes', 'unidades');

        // 2. Modificamos la estructura de la nueva tabla 'unidades'
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('numero')->after('id'); // Ej: "101", "A-203"
            $table->string('propietario')->after('nombre'); // Nombre del dueño
            $table->renameColumn('nombre', 'residente'); // 'nombre' ahora es 'residente' (arrendatario)
            $table->decimal('prorrateo', 8, 5)->after('telefono'); // El % de participación. Ej: 0.12345
            $table->foreignId('id_grupo')->nullable()->constrained('grupos')->onDelete('set null');
            $table->foreignId('residente_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Esto revierte los cambios si es necesario
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn(['numero', 'propietario', 'prorrateo']);
            $table->renameColumn('residente', 'nombre');
        });
        Schema::rename('unidades', 'clientes');
    }
};