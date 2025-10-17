<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            // Añadimos la columna para la contraseña del residente después del email.
            // Es nullable() porque podrías tener unidades sin acceso al portal.
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};