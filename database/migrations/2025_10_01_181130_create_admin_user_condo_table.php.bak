<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_user_condo')) {
            Schema::create('admin_user_condo', function (Blueprint $table) {
                $table->bigIncrements('id');

                // No ponemos FKs rígidas para que funcione con 'usuario' o 'users' indistintamente
                $table->unsignedBigInteger('id_usuario');     // id del admin (puede venir de 'usuario.id_usuario' o 'users.id')
                $table->unsignedBigInteger('id_condominio');  // condominio

                $table->timestamps();

                $table->unique(['id_usuario', 'id_condominio'], 'uac_unique');
                $table->index('id_usuario');
                $table->index('id_condominio');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_condo');
    }
};
