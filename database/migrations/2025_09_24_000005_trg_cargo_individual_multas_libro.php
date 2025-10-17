<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Limpieza por si existía una versión anterior
        DB::unprepared("DROP TRIGGER IF EXISTS trg_cargo_indv_ai_libro;");

        /*
         * Al crear un cargo individual de tipo multa/interés:
         *   Debe:  CxC (1201)
         *   Haber: Ingresos por Multas (4202) o Intereses (4203)
         * Fecha contable: primer día del periodo (AAAAMM) o hoy si no viene periodo.
         */
        DB::unprepared("
        CREATE TRIGGER trg_cargo_indv_ai_libro
        AFTER INSERT ON cargo_individual
        FOR EACH ROW
        BEGIN
            DECLARE v_condo BIGINT;
            DECLARE v_cta_cxc SMALLINT;
            DECLARE v_cta_ing SMALLINT;
            DECLARE v_fecha DATE;
            DECLARE v_glosa VARCHAR(300);
            DECLARE v_cod_ing VARCHAR(10);

            -- Solo aplica a multas / intereses / mora / recargo
            IF (LOWER(NEW.tipo) IN ('multa','interes','mora','recargo')) THEN
                -- Condominio de la unidad
                SELECT g.id_condominio
                  INTO v_condo
                FROM unidad u
                JOIN grupo  g ON g.id_grupo = u.id_grupo
                WHERE u.id_unidad = NEW.id_unidad
                LIMIT 1;

                -- Cuentas contables (ajusta códigos si usas otros)
                SELECT id_cta_contable INTO v_cta_cxc FROM cuenta_contable WHERE codigo = '1201' LIMIT 1;
                SET v_cod_ing = CASE
                                  WHEN LOWER(NEW.tipo) IN ('interes','mora') THEN '4203'  -- <== intereses
                                  ELSE '4202'                                              -- <== multas/recargos
                                END;
                SELECT id_cta_contable INTO v_cta_ing FROM cuenta_contable WHERE codigo = v_cod_ing LIMIT 1;

                -- Fecha contable: 1° del periodo si viene AAAAMM, si no hoy
                IF NEW.periodo IS NOT NULL AND CHAR_LENGTH(NEW.periodo) = 6 THEN
                    SET v_fecha = STR_TO_DATE(CONCAT(NEW.periodo,'01'), '%Y%m%d');
                ELSE
                    SET v_fecha = CURDATE();
                END IF;

                -- Glosa
                SET v_glosa = CONCAT('Cargo ', UPPER(NEW.tipo),
                                     ' unidad ', NEW.id_unidad,
                                     ' periodo ', IFNULL(NEW.periodo,'-'),
                                     IF(NEW.referencia IS NULL OR NEW.referencia = '', '', CONCAT(' (', NEW.referencia, ')'))
                                    );

                -- Insert doble partida si todo está seteado y monto > 0
                IF v_condo IS NOT NULL AND v_cta_cxc IS NOT NULL AND v_cta_ing IS NOT NULL AND NEW.monto > 0 THEN
                    INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                    VALUES (v_condo, v_fecha, v_cta_cxc, NEW.monto, 0, 'cargo_individual', NEW.id_cargo_indv, v_glosa);

                    INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                    VALUES (v_condo, v_fecha, v_cta_ing, 0, NEW.monto, 'cargo_individual', NEW.id_cargo_indv, v_glosa);
                END IF;
            END IF;
        END;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_cargo_indv_ai_libro;");
    }
};
