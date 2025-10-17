<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration {
    public function up(): void
    {
        $path = database_path('sql/schema.sql');
        if (!File::exists($path)) throw new RuntimeException("Falta database/sql/schema.sql");
        DB::unprepared(File::get($path));
    }

    public function down(): void
    {
        DB::unprepared(<<<SQL
SET UNIQUE_CHECKS=0; SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS pasarela_tx, pago_aplicacion, comprobante_pago, pago, cobro_detalle, cobro,
  cargo_individual, cargo_unidad, prorrateo_factor_unidad, prorrateo_regla, interes_regla,
  resumen_mensual, remuneracion, trabajador_contrato, trabajador, residencia, copropietario,
  usuario_admin_condo, usuario, libro_movimiento, fondo_reserva_mov, auditoria, gasto,
  gasto_categoria, proveedor, unidad, grupo, condominio_anexo_regla, condominio,
  cuenta_contable, param_reglamento;
DROP TABLE IF EXISTS cat_cobro_estado, cat_vivienda_subtipo, cat_unidad_tipo, cat_segmento,
  cat_doc_tipo, cat_concepto_cargo, cat_metodo_pago, cat_pasarela, cat_estado_tx, cat_tipo_cuenta;
DROP FUNCTION IF EXISTS fn_valida_dv;
SET FOREIGN_KEY_CHECKS=1; SET UNIQUE_CHECKS=1;
SQL);
    }
};
