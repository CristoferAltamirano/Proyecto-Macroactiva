<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Limpieza previa (por si existían versiones anteriores)
        DB::unprepared("DROP TRIGGER IF EXISTS trg_pago_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_gasto_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_remu_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_remu_au_pago_libro;");

        /* ===========================
           PAGO: Banco (debe) vs CxC (haber)
           =========================== */
        DB::unprepared("
        CREATE TRIGGER trg_pago_ai_libro
        AFTER INSERT ON pago
        FOR EACH ROW
        BEGIN
            DECLARE v_condo BIGINT;
            DECLARE v_cta_banco SMALLINT;
            DECLARE v_cta_cxc SMALLINT;
            DECLARE v_glosa VARCHAR(300);

            SELECT g.id_condominio INTO v_condo
            FROM unidad u
            JOIN grupo g ON g.id_grupo = u.id_grupo
            WHERE u.id_unidad = NEW.id_unidad
            LIMIT 1;

            SELECT id_cta_contable INTO v_cta_banco FROM cuenta_contable WHERE codigo='1101' LIMIT 1;
            SELECT id_cta_contable INTO v_cta_cxc   FROM cuenta_contable WHERE codigo='1201' LIMIT 1;

            SET v_glosa = CONCAT('Pago unidad ', NEW.id_unidad, ' periodo ', IFNULL(NEW.periodo,'-'));

            IF v_condo IS NOT NULL AND v_cta_banco IS NOT NULL AND v_cta_cxc IS NOT NULL THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (v_condo, NEW.fecha_pago, v_cta_banco, NEW.monto, 0, 'pago', NEW.id_pago, v_glosa);

                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (v_condo, NEW.fecha_pago, v_cta_cxc, 0, NEW.monto, 'pago', NEW.id_pago, v_glosa);
            END IF;
        END;
        ");

        /* ===========================
           GASTO: Gasto + IVA (debe) vs Proveedores (haber)
           =========================== */
        DB::unprepared("
        CREATE TRIGGER trg_gasto_ai_libro
        AFTER INSERT ON gasto
        FOR EACH ROW
        BEGIN
            DECLARE v_cta_gasto SMALLINT;
            DECLARE v_cta_iva   SMALLINT;
            DECLARE v_cta_prov  SMALLINT;
            DECLARE v_glosa VARCHAR(300);

            SELECT id_cta_contable INTO v_cta_gasto FROM cuenta_contable WHERE codigo='5101' LIMIT 1;
            SELECT id_cta_contable INTO v_cta_iva   FROM cuenta_contable WHERE codigo='1191' LIMIT 1;
            SELECT id_cta_contable INTO v_cta_prov  FROM cuenta_contable WHERE codigo='2101' LIMIT 1;

            SET v_glosa = CONCAT('Gasto ', IFNULL(NEW.descripcion,''), ' folio ', IFNULL(NEW.documento_folio,''));

            IF v_cta_gasto IS NOT NULL AND NEW.neto > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (NEW.id_condominio, IFNULL(NEW.fecha_emision, NOW()), v_cta_gasto, NEW.neto, 0, 'gasto', NEW.id_gasto, v_glosa);
            END IF;

            IF v_cta_iva IS NOT NULL AND NEW.iva > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (NEW.id_condominio, IFNULL(NEW.fecha_emision, NOW()), v_cta_iva, NEW.iva, 0, 'gasto', NEW.id_gasto, v_glosa);
            END IF;

            IF v_cta_prov IS NOT NULL AND (NEW.neto + NEW.iva) > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (NEW.id_condominio, IFNULL(NEW.fecha_emision, NOW()), v_cta_prov, 0, NEW.total, 'gasto', NEW.id_gasto, v_glosa);
            END IF;
        END;
        ");

        /* ===========================
           REMUNERACIÓN (al crear):
           Debe: Gasto remuneraciones (bruto)
           Haber: Remuneraciones por pagar (líquido)
           Haber: Imposiciones por pagar (imposiciones)
           =========================== */
        DB::unprepared("
        CREATE TRIGGER trg_remu_ai_libro
        AFTER INSERT ON remuneracion
        FOR EACH ROW
        BEGIN
            DECLARE v_condo BIGINT;
            DECLARE v_cta_gremu SMALLINT;
            DECLARE v_cta_rem_p SMALLINT;
            DECLARE v_cta_imp_p SMALLINT;
            DECLARE v_glosa VARCHAR(300);

            SELECT t.id_condominio INTO v_condo
            FROM trabajador t
            WHERE t.id_trabajador = NEW.id_trabajador LIMIT 1;

            SELECT id_cta_contable INTO v_cta_gremu FROM cuenta_contable WHERE codigo='5102' LIMIT 1;
            SELECT id_cta_contable INTO v_cta_rem_p FROM cuenta_contable WHERE codigo='2102' LIMIT 1;
            SELECT id_cta_contable INTO v_cta_imp_p FROM cuenta_contable WHERE codigo='2104' LIMIT 1;

            SET v_glosa = CONCAT('Remuneración ', NEW.tipo, ' periodo ', NEW.periodo);

            IF v_condo IS NOT NULL AND v_cta_gremu IS NOT NULL AND NEW.bruto > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (v_condo, IFNULL(NEW.fecha_pago, NOW()), v_cta_gremu, NEW.bruto, 0, 'remuneracion', NEW.id_remuneracion, v_glosa);
            END IF;

            IF v_condo IS NOT NULL AND v_cta_rem_p IS NOT NULL AND NEW.liquido > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (v_condo, IFNULL(NEW.fecha_pago, NOW()), v_cta_rem_p, 0, NEW.liquido, 'remuneracion', NEW.id_remuneracion, v_glosa);
            END IF;

            IF v_condo IS NOT NULL AND v_cta_imp_p IS NOT NULL AND NEW.imposiciones > 0 THEN
                INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                VALUES (v_condo, IFNULL(NEW.fecha_pago, NOW()), v_cta_imp_p, 0, NEW.imposiciones, 'remuneracion', NEW.id_remuneracion, v_glosa);
            END IF;
        END;
        ");

        /* ===========================
           REMUNERACIÓN (al pagar):
           Debe: Rem por pagar (líquido)
           Debe: Imposiciones por pagar
           Haber: Banco (total)
           =========================== */
        DB::unprepared("
        CREATE TRIGGER trg_remu_au_pago_libro
        AFTER UPDATE ON remuneracion
        FOR EACH ROW
        BEGIN
            DECLARE v_condo BIGINT;
            DECLARE v_cta_banco SMALLINT;
            DECLARE v_cta_rem_p SMALLINT;
            DECLARE v_cta_imp_p SMALLINT;
            DECLARE v_total_pago DECIMAL(12,2);
            DECLARE v_glosa VARCHAR(300);

            IF (OLD.fecha_pago IS NULL AND NEW.fecha_pago IS NOT NULL) THEN
                SELECT t.id_condominio INTO v_condo
                FROM trabajador t
                WHERE t.id_trabajador = NEW.id_trabajador LIMIT 1;

                SELECT id_cta_contable INTO v_cta_banco FROM cuenta_contable WHERE codigo='1101' LIMIT 1;
                SELECT id_cta_contable INTO v_cta_rem_p FROM cuenta_contable WHERE codigo='2102' LIMIT 1;
                SELECT id_cta_contable INTO v_cta_imp_p FROM cuenta_contable WHERE codigo='2104' LIMIT 1;

                SET v_total_pago = NEW.liquido + NEW.imposiciones;
                SET v_glosa = CONCAT('Pago remuneración periodo ', NEW.periodo);

                IF v_condo IS NOT NULL THEN
                    IF v_cta_rem_p IS NOT NULL AND NEW.liquido > 0 THEN
                        INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                        VALUES (v_condo, NEW.fecha_pago, v_cta_rem_p, NEW.liquido, 0, 'remuneracion', NEW.id_remuneracion, v_glosa);
                    END IF;

                    IF v_cta_imp_p IS NOT NULL AND NEW.imposiciones > 0 THEN
                        INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                        VALUES (v_condo, NEW.fecha_pago, v_cta_imp_p, NEW.imposiciones, 0, 'remuneracion', NEW.id_remuneracion, v_glosa);
                    END IF;

                    IF v_cta_banco IS NOT NULL AND v_total_pago > 0 THEN
                        INSERT INTO libro_movimiento (id_condominio, fecha, id_cta_contable, debe, haber, ref_tabla, ref_id, glosa)
                        VALUES (v_condo, NEW.fecha_pago, v_cta_banco, 0, v_total_pago, 'remuneracion', NEW.id_remuneracion, v_glosa);
                    END IF;
                END IF;
            END IF;
        END;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_pago_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_gasto_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_remu_ai_libro;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_remu_au_pago_libro;");
    }
};
