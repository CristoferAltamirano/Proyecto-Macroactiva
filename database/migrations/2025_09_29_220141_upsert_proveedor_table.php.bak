<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proveedor')) {
            // ===== Crear tabla completa (si no existe) =====
            Schema::create('proveedor', function (Blueprint $table) {
                $table->bigIncrements('id_proveedor');

                $table->enum('tipo', ['persona','empresa'])->default('empresa');
                $table->unsignedInteger('rut_base');
                $table->char('rut_dv', 1);

                $table->string('nombre', 140);
                $table->string('giro', 140)->nullable();
                $table->string('email', 140)->nullable();
                $table->string('telefono', 40)->nullable();

                // Contacto de la empresa
                $table->string('contacto_nombre', 140)->nullable();
                $table->string('contacto_email', 140)->nullable();
                $table->string('contacto_telefono', 40)->nullable();

                // Persona que "atiende" (ejecutivo)
                $table->string('ejecutivo_nombre', 140)->nullable();
                $table->string('ejecutivo_email', 140)->nullable();
                $table->string('ejecutivo_telefono', 40)->nullable();

                // Índice único por RUT
                $table->unique(['rut_base', 'rut_dv'], 'uk_proveedor_rut');
            });
            return;
        }

        // ===== Si la tabla ya existe: agregar solo las columnas faltantes =====
        Schema::table('proveedor', function (Blueprint $table) {
            // contacto de empresa
            if (!Schema::hasColumn('proveedor', 'contacto_nombre')) {
                $table->string('contacto_nombre', 140)->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('proveedor', 'contacto_email')) {
                $table->string('contacto_email', 140)->nullable()->after('contacto_nombre');
            }
            if (!Schema::hasColumn('proveedor', 'contacto_telefono')) {
                $table->string('contacto_telefono', 40)->nullable()->after('contacto_email');
            }

            // ejecutivo que atiende
            if (!Schema::hasColumn('proveedor', 'ejecutivo_nombre')) {
                $table->string('ejecutivo_nombre', 140)->nullable()->after('contacto_telefono');
            }
            if (!Schema::hasColumn('proveedor', 'ejecutivo_email')) {
                $table->string('ejecutivo_email', 140)->nullable()->after('ejecutivo_nombre');
            }
            if (!Schema::hasColumn('proveedor', 'ejecutivo_telefono')) {
                $table->string('ejecutivo_telefono', 40)->nullable()->after('ejecutivo_email');
            }
        });

        // (Opcional) si tu tabla original no tuviese el índice único de RUT, puedes habilitar esto:
        // try {
        //     DB::statement('ALTER TABLE proveedor ADD UNIQUE KEY uk_proveedor_rut (rut_base, rut_dv)');
        // } catch (\Throwable $e) { /* ya existe, ignorar */ }
    }

    public function down(): void
    {
        // Si la tabla fue creada por esta migración, puedes borrarla;
        // si ya existía, eliminamos solo las columnas nuevas.
        if (!Schema::hasTable('proveedor')) return;

        // ¿Fue creada por esta migración? No lo sabemos con certeza,
        // así que hacemos un "rollback seguro" quitando solo columnas nuevas.
        Schema::table('proveedor', function (Blueprint $table) {
            foreach ([
                'contacto_nombre', 'contacto_email', 'contacto_telefono',
                'ejecutivo_nombre', 'ejecutivo_email', 'ejecutivo_telefono',
            ] as $col) {
                if (Schema::hasColumn('proveedor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
