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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_unidad')->nullable();
            $table->foreign('id_unidad')
                  ->references('id_unidad')
                  ->on('unidades')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The dropForeign method requires the index name, which by convention is users_id_unidad_foreign
            $table->dropForeign('users_id_unidad_foreign');
            $table->dropColumn('id_unidad');
        });
    }
};