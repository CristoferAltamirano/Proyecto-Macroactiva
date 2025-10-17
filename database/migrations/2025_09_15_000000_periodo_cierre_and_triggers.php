<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('periodo_cierre', function(Blueprint $t){
            $t->id('id_cierre')->startingValue(1);
            $t->unsignedBigInteger('id_condominio');
            $t->char('periodo',6); // AAAAMM
            $t->unsignedBigInteger('cerrado_por')->nullable();
            $t->timestamp('cerrado_at')->useCurrent();
            $t->unique(['id_condominio','periodo'], 'uk_cierre_condo_periodo');
            $t->index(['periodo']);
        });

        // Utilidad SQL para obtener condominio desde unidad
        DB::unprepared("
        CREATE OR REPLACE VIEW vw_unidad_condo AS
        SELECT u.id_unidad, g.id_condominio
        FROM unidad u
        LEFT JOIN grupo g ON g.id_grupo = u.id_grupo
        ");

        /* ========== TRIGGERS DE BLOQUEO POR PERIODO ========== */
        // Helper: check de cierre
        $checkPago = "
            DECLARE v_cid BIGINT; DECLARE v_per CHAR(6);
            IF NEW.id_unidad IS NOT NULL THEN
              SELECT id_condominio INTO v_cid FROM vw_unidad_condo WHERE id_unidad = NEW.id_unidad LIMIT 1;
            ELSE
              SET v_cid = NULL;
            END IF;
            SET v_per = COALESCE(NEW.periodo, DATE_FORMAT(NEW.fecha_pago,'%Y%m'));
            IF v_per IS NOT NULL AND v_cid IS NOT NULL THEN
              IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=v_cid AND periodo=v_per) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar pagos en ese periodo';
              END IF;
            END IF;
        ";

        $checkCU = "
            DECLARE v_cid BIGINT;
            SELECT id_condominio INTO v_cid FROM vw_unidad_condo WHERE id_unidad = NEW.id_unidad LIMIT 1;
            IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=v_cid AND periodo=NEW.periodo) THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar cargos_unidad en ese periodo';
            END IF;
        ";

        $checkCI = "
            DECLARE v_cid BIGINT;
            SELECT id_condominio INTO v_cid FROM vw_unidad_condo WHERE id_unidad = NEW.id_unidad LIMIT 1;
            IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=v_cid AND periodo=NEW.periodo) THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar cargos_individuales en ese periodo';
            END IF;
        ";

        $checkCobro = "
            DECLARE v_cid BIGINT;
            SELECT id_condominio INTO v_cid FROM vw_unidad_condo WHERE id_unidad = NEW.id_unidad LIMIT 1;
            IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=v_cid AND periodo=NEW.periodo) THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar cobros en ese periodo';
            END IF;
        ";

        $checkGasto = "
            IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=NEW.id_condominio AND periodo=NEW.periodo) THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar gastos en ese periodo';
            END IF;
        ";

        $checkRemu = "
            DECLARE v_cid BIGINT;
            SELECT id_condominio INTO v_cid FROM trabajador WHERE id_trabajador = NEW.id_trabajador LIMIT 1;
            IF EXISTS(SELECT 1 FROM periodo_cierre WHERE id_condominio=v_cid AND periodo=NEW.periodo) THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periodo cerrado: no se puede modificar remuneraciones en ese periodo';
            END IF;
        ";

        // PAGOS
        DB::unprepared("CREATE TRIGGER trg_pago_bi_cierre BEFORE INSERT ON pago FOR EACH ROW BEGIN {$checkPago} END;");
        DB::unprepared("CREATE TRIGGER trg_pago_bu_cierre BEFORE UPDATE ON pago FOR EACH ROW BEGIN {$checkPago} END;");

        // CARGO_UNIDAD
        DB::unprepared("CREATE TRIGGER trg_cu_bi_cierre BEFORE INSERT ON cargo_unidad FOR EACH ROW BEGIN {$checkCU} END;");
        DB::unprepared("CREATE TRIGGER trg_cu_bu_cierre BEFORE UPDATE ON cargo_unidad FOR EACH ROW BEGIN {$checkCU} END;");

        // CARGO_INDIVIDUAL
        DB::unprepared("CREATE TRIGGER trg_ci_bi_cierre BEFORE INSERT ON cargo_individual FOR EACH ROW BEGIN {$checkCI} END;");
        DB::unprepared("CREATE TRIGGER trg_ci_bu_cierre BEFORE UPDATE ON cargo_individual FOR EACH ROW BEGIN {$checkCI} END;");

        // COBRO
        DB::unprepared("CREATE TRIGGER trg_cobro_bi_cierre BEFORE INSERT ON cobro FOR EACH ROW BEGIN {$checkCobro} END;");
        DB::unprepared("CREATE TRIGGER trg_cobro_bu_cierre BEFORE UPDATE ON cobro FOR EACH ROW BEGIN {$checkCobro} END;");

        // GASTO
        DB::unprepared("CREATE TRIGGER trg_gasto_bi_cierre BEFORE INSERT ON gasto FOR EACH ROW BEGIN {$checkGasto} END;");
        DB::unprepared("CREATE TRIGGER trg_gasto_bu_cierre BEFORE UPDATE ON gasto FOR EACH ROW BEGIN {$checkGasto} END;");

        // REMUNERACION
        DB::unprepared("CREATE TRIGGER trg_remu_bi_cierre BEFORE INSERT ON remuneracion FOR EACH ROW BEGIN {$checkRemu} END;");
        DB::unprepared("CREATE TRIGGER trg_remu_bu_cierre BEFORE UPDATE ON remuneracion FOR EACH ROW BEGIN {$checkRemu} END;");
    }

    public function down(): void
    {
        // Eliminar triggers
        foreach ([
            'trg_pago_bi_cierre','trg_pago_bu_cierre',
            'trg_cu_bi_cierre','trg_cu_bu_cierre',
            'trg_ci_bi_cierre','trg_ci_bu_cierre',
            'trg_cobro_bi_cierre','trg_cobro_bu_cierre',
            'trg_gasto_bi_cierre','trg_gasto_bu_cierre',
            'trg_remu_bi_cierre','trg_remu_bu_cierre',
        ] as $trg) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trg};");
        }
        DB::statement("DROP VIEW IF EXISTS vw_unidad_condo");
        Schema::dropIfExists('periodo_cierre');
    }
};
