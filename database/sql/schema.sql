-- =============================================================================
-- MacroActivaSPA – Esquema de datos (MySQL 8.0+)
-- Ajustes aplicados:
--  - Roles: ENUM('super_admin','copropietario','residente') en tabla usuario
--  - Sin tablas de rol/usuario_rol
--  - CHECKs compatibles con MySQL (sin llamadas a funciones)
--  - TRIGGERS para validar RUT usando fn_valida_dv
--  - Seeds mínimos de catálogos
--  - Sin CREATE SCHEMA / USE / DELIMITER (para Laravel DB::unprepared)
-- =============================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET @OLD_SQL_MODE=@@SQL_MODE;

SET UNIQUE_CHECKS=0;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- Limpieza (drop if exists) - orden seguro
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS pasarela_tx, pago_aplicacion, comprobante_pago, pago, cobro_detalle, cobro,
  cargo_individual, cargo_unidad, prorrateo_factor_unidad, prorrateo_regla, interes_regla,
  resumen_mensual, remuneracion, trabajador_contrato, trabajador, residencia, copropietario,
  usuario_admin_condo, usuario, libro_movimiento, fondo_reserva_mov, auditoria, gasto,
  gasto_categoria, proveedor, unidad, grupo, condominio_anexo_regla, condominio,
  cuenta_contable, param_reglamento;

DROP TABLE IF EXISTS cat_cobro_estado, cat_vivienda_subtipo, cat_unidad_tipo, cat_segmento,
  cat_doc_tipo, cat_concepto_cargo, cat_metodo_pago, cat_pasarela, cat_estado_tx, cat_tipo_cuenta;

DROP FUNCTION IF EXISTS fn_valida_dv;

-- -----------------------------------------------------------------------------
-- Función RUT (módulo 11)
-- -----------------------------------------------------------------------------
CREATE FUNCTION fn_valida_dv(rut_base INT, dv CHAR(1))
RETURNS TINYINT
DETERMINISTIC
NO SQL
SQL SECURITY INVOKER
BEGIN
  DECLARE s INT DEFAULT 0; DECLARE m INT DEFAULT 2; DECLARE x INT; DECLARE r INT;
  IF rut_base IS NULL OR dv IS NULL OR rut_base <= 0 THEN RETURN 0; END IF;
  SET x = rut_base;
  WHILE x > 0 DO
    SET s = s + (x % 10) * m; SET x = FLOOR(x/10); SET m = IF(m=7,2,m+1);
  END WHILE;
  SET r = 11 - (s % 11);
  RETURN ( (r=11 AND UPPER(dv)='0') OR (r=10 AND UPPER(dv)='K') OR (r<10 AND UPPER(dv)=CAST(r AS CHAR)) );
END;

-- -----------------------------------------------------------------------------
-- Catálogos base
-- -----------------------------------------------------------------------------
CREATE TABLE cat_tipo_cuenta (
  id_tipo_cuenta TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_tipo_cuenta),
  UNIQUE KEY uk_cat_tipocuenta (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_doc_tipo (
  id_doc_tipo TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_doc_tipo),
  UNIQUE KEY uk_cat_doctipo (codigo)
) ENGINE=InnoDB;

CREATE TABLE gasto_categoria (
  id_gasto_categ SMALLINT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_gasto_categ),
  UNIQUE KEY uk_gasto_categ (nombre)
) ENGINE=InnoDB;

CREATE TABLE cat_metodo_pago (
  id_metodo_pago TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_metodo_pago),
  UNIQUE KEY uk_cat_metodo (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_pasarela (
  id_pasarela TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_pasarela),
  UNIQUE KEY uk_cat_pasarela (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_estado_tx (
  id_estado_tx TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_estado_tx),
  UNIQUE KEY uk_cat_estado_tx (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_segmento (
  id_segmento TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL, -- 'residencial' | 'comercial'
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_segmento),
  UNIQUE KEY uk_cat_segmento (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_unidad_tipo (
  id_unidad_tipo TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL, -- 'vivienda' | 'bodega' | 'estacionamiento'
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_unidad_tipo),
  UNIQUE KEY uk_cat_unidad_tipo (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_vivienda_subtipo (
  id_viv_subtipo TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL, -- 'casa' | 'departamento' | 'local'
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_viv_subtipo),
  UNIQUE KEY uk_cat_viv_subtipo (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_cobro_estado (
  id_cobro_estado TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL, -- 'emitido','parcial','pagado','anulado'
  PRIMARY KEY (id_cobro_estado),
  UNIQUE KEY uk_cat_cobro_estado (codigo)
) ENGINE=InnoDB;

CREATE TABLE cat_concepto_cargo (
  id_concepto_cargo TINYINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  nombre VARCHAR(60) NULL,
  PRIMARY KEY (id_concepto_cargo),
  UNIQUE KEY uk_cat_concepto (codigo)
) ENGINE=InnoDB;

-- Seeds mínimos
INSERT INTO cat_segmento (codigo, nombre) VALUES
 ('residencial','Residencial'),
 ('comercial','Comercial')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO cat_unidad_tipo (codigo, nombre) VALUES
 ('vivienda','Vivienda'),
 ('bodega','Bodega'),
 ('estacionamiento','Estacionamiento')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO cat_vivienda_subtipo (codigo, nombre) VALUES
 ('casa','Casa'),
 ('departamento','Departamento'),
 ('local','Local')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO cat_cobro_estado (codigo) VALUES
 ('emitido'),('parcial'),('pagado'),('anulado')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO cat_concepto_cargo (codigo, nombre) VALUES
 ('vivienda','Vivienda'),
 ('bodega','Bodega'),
 ('estacionamiento','Estacionamiento'),
 ('remuneraciones','Remuneraciones'),
 ('proveedores','Proveedores')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- Extras útiles
INSERT INTO cat_tipo_cuenta (codigo) VALUES ('corriente'),('vista'),('ahorro')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO cat_doc_tipo (codigo) VALUES ('boleta'),('factura'),('nc')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO cat_metodo_pago (codigo, nombre) VALUES
 ('transferencia','Transferencia'),
 ('tarjeta','Tarjeta'),
 ('efectivo','Efectivo'),
 ('webpay','WebPay')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO cat_pasarela (codigo) VALUES ('webpay'),('flow')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO cat_estado_tx (codigo) VALUES ('iniciada'),('aprobada'),('rechazada'),('fallida')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

-- -----------------------------------------------------------------------------
-- Núcleo condominio / anexos / grupos / unidades
-- -----------------------------------------------------------------------------
CREATE TABLE condominio (
  id_condominio BIGINT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  rut_base INT NULL,
  rut_dv CHAR(1) NULL,
  direccion VARCHAR(200) NULL,
  comuna VARCHAR(80) NULL,
  region VARCHAR(80) NULL,
  email_contacto VARCHAR(120) NULL,
  telefono VARCHAR(40) NULL,
  banco VARCHAR(80) NULL,
  id_tipo_cuenta TINYINT NULL,
  num_cuenta VARCHAR(40) NULL,
  PRIMARY KEY (id_condominio),
  UNIQUE KEY uk_condominio_nombre (nombre),
  CONSTRAINT fk_condo_tipocuenta FOREIGN KEY (id_tipo_cuenta) REFERENCES cat_tipo_cuenta(id_tipo_cuenta) ON DELETE SET NULL ON UPDATE RESTRICT,
  -- En MySQL no se puede llamar función en CHECK; sólo validamos nulidad/coherencia aquí.
  CONSTRAINT chk_condo_rut CHECK (
    (rut_base IS NULL AND rut_dv IS NULL) OR (rut_base IS NOT NULL AND rut_dv IS NOT NULL)
  ),
  CONSTRAINT chk_condo_email CHECK (email_contacto IS NULL OR email_contacto REGEXP '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$')
) ENGINE=InnoDB;

CREATE TABLE condominio_anexo_regla (
  id_regla BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  id_viv_subtipo TINYINT NULL, -- NULL = aplica a todos
  anexo_tipo ENUM('bodega','estacionamiento') NOT NULL,
  incluido_qty TINYINT NOT NULL DEFAULT 0,
  cobrable_por_sobre_qty TINYINT(1) NOT NULL DEFAULT 1,
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NULL,
  comentario VARCHAR(200) NULL,
  PRIMARY KEY (id_regla),
  KEY ix_car_rango (id_condominio, id_viv_subtipo, anexo_tipo, vigente_desde, vigente_hasta),
  CONSTRAINT fk_car_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_car_viv_subtipo FOREIGN KEY (id_viv_subtipo) REFERENCES cat_vivienda_subtipo(id_viv_subtipo) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_car_incluido CHECK (incluido_qty >= 0)
) ENGINE=InnoDB;

CREATE TABLE grupo (
  id_grupo BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  tipo VARCHAR(45) NOT NULL, -- torre, manzana, bloque, etc.
  PRIMARY KEY (id_grupo),
  UNIQUE KEY uk_grupo_nombre (id_condominio, nombre),
  CONSTRAINT fk_grupo_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE unidad (
  id_unidad BIGINT NOT NULL AUTO_INCREMENT,
  id_grupo BIGINT NULL,
  codigo VARCHAR(40) NOT NULL,
  direccion VARCHAR(200) NULL,
  id_unidad_tipo TINYINT NULL, -- vivienda/bodega/estacionamiento
  id_viv_subtipo TINYINT NULL, -- solo si vivienda
  id_segmento TINYINT NULL,    -- residencial/comercial
  anexo_incluido TINYINT(1) NOT NULL DEFAULT 0,
  anexo_cobrable TINYINT(1) NOT NULL DEFAULT 0,
  rol_sii VARCHAR(40) NULL,
  metros2 DECIMAL(10,2) NULL,
  coef_prop DECIMAL(8,6) NOT NULL,
  habitable TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_unidad),
  UNIQUE KEY uk_unidad_codigo (id_grupo, codigo),
  KEY ix_unidad_grupo (id_grupo),
  KEY ix_unidad_tipo_seg (id_unidad_tipo, id_segmento),
  CONSTRAINT fk_unidad_grupo FOREIGN KEY (id_grupo) REFERENCES grupo(id_grupo) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_unidad_tipo FOREIGN KEY (id_unidad_tipo) REFERENCES cat_unidad_tipo(id_unidad_tipo) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_unidad_viv_subtipo FOREIGN KEY (id_viv_subtipo) REFERENCES cat_vivienda_subtipo(id_viv_subtipo) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_unidad_segmento FOREIGN KEY (id_segmento) REFERENCES cat_segmento(id_segmento) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT chk_unidad_coef CHECK (coef_prop >= 0 AND coef_prop <= 1),
  CONSTRAINT chk_unidad_anexo_flags CHECK (NOT (anexo_incluido=1 AND anexo_cobrable=1))
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Usuario (roles embebidos)
-- -----------------------------------------------------------------------------
CREATE TABLE usuario (
  id_usuario BIGINT NOT NULL AUTO_INCREMENT,
  tipo_usuario ENUM('super_admin','copropietario','residente') NOT NULL DEFAULT 'residente',
  rut_base INT NOT NULL,
  rut_dv CHAR(1) NOT NULL,
  nombres VARCHAR(120) NOT NULL,
  apellidos VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(40) NULL,
  direccion VARCHAR(200) NULL,
  pass_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uk_usuario_email (email),
  UNIQUE KEY uk_usuario_rut (rut_base, rut_dv),
  -- Validación mínima en CHECK; la validación completa via TRIGGER
  CONSTRAINT chk_usuario_rut CHECK (rut_base > 0 AND rut_dv REGEXP '^[0-9Kk]$'),
  CONSTRAINT chk_usuario_email CHECK (email REGEXP '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$')
) ENGINE=InnoDB;

CREATE TABLE usuario_admin_condo (
  id_usuario BIGINT NOT NULL,
  id_condominio BIGINT NOT NULL,
  PRIMARY KEY (id_usuario, id_condominio),
  CONSTRAINT fk_uac_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_uac_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Copropietario / Residencia
-- -----------------------------------------------------------------------------
CREATE TABLE copropietario (
  id_coprop BIGINT NOT NULL AUTO_INCREMENT,
  id_usuario BIGINT NOT NULL,
  id_unidad BIGINT NOT NULL,
  porcentaje DECIMAL(6,3) NOT NULL, -- 0..100
  desde DATE NOT NULL,
  hasta DATE NULL,
  PRIMARY KEY (id_coprop),
  UNIQUE KEY uk_coprop_vig (id_unidad, id_usuario, desde),
  KEY ix_coprop_busq (id_unidad, desde, hasta),
  CONSTRAINT fk_coprop_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_coprop_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_coprop_pct CHECK (porcentaje >= 0 AND porcentaje <= 100)
) ENGINE=InnoDB;

CREATE TABLE residencia (
  id_residencia BIGINT NOT NULL AUTO_INCREMENT,
  id_unidad BIGINT NOT NULL,
  id_usuario BIGINT NOT NULL,
  origen ENUM('propietario','arrendatario') NOT NULL,
  desde DATE NOT NULL,
  hasta DATE NULL,
  observacion VARCHAR(200) NULL,
  PRIMARY KEY (id_residencia),
  UNIQUE KEY uk_residencia_vig (id_unidad, id_usuario, desde),
  KEY ix_residencia_busq (id_unidad, desde, hasta),
  CONSTRAINT fk_res_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_res_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Proveedores / Contabilidad / Reglamento
-- -----------------------------------------------------------------------------
CREATE TABLE proveedor (
  id_proveedor BIGINT NOT NULL AUTO_INCREMENT,
  tipo ENUM('persona','empresa') NOT NULL DEFAULT 'empresa',
  rut_base INT NOT NULL,
  rut_dv CHAR(1) NOT NULL,
  nombre VARCHAR(140) NOT NULL,
  giro VARCHAR(140) NULL,
  email VARCHAR(120) NULL,
  telefono VARCHAR(40) NULL,
  PRIMARY KEY (id_proveedor),
  UNIQUE KEY uk_proveedor_rut (rut_base, rut_dv),
  CONSTRAINT chk_prov_rut CHECK (rut_base > 0 AND rut_dv REGEXP '^[0-9Kk]$'),
  CONSTRAINT chk_prov_email CHECK (email IS NULL OR email REGEXP '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$')
) ENGINE=InnoDB;

CREATE TABLE cuenta_contable (
  id_cta_contable SMALLINT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  PRIMARY KEY (id_cta_contable),
  UNIQUE KEY uk_cta_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE param_reglamento (
  id_condominio BIGINT NOT NULL,
  recargo_fondo_reserva_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  interes_mora_anual_pct DECIMAL(6,3) NULL,
  dias_gracia TINYINT NOT NULL DEFAULT 0,
  multa_morosidad_fija DECIMAL(12,2) NULL,
  PRIMARY KEY (id_condominio),
  CONSTRAINT fk_regla_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Intereses por mora
-- -----------------------------------------------------------------------------
CREATE TABLE interes_regla (
  id_interes_regla BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  id_segmento TINYINT NOT NULL, -- residencial/comercial
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NULL,
  tasa_anual_pct DECIMAL(6,3) NOT NULL,
  dias_gracia TINYINT NOT NULL DEFAULT 0,
  fuente_url VARCHAR(300) NULL,
  comentario VARCHAR(300) NULL,
  PRIMARY KEY (id_interes_regla),
  KEY ix_ir_rango (id_condominio, id_segmento, vigente_desde, vigente_hasta),
  CONSTRAINT fk_ir_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_ir_segmento FOREIGN KEY (id_segmento) REFERENCES cat_segmento(id_segmento) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_ir_tasa CHECK (tasa_anual_pct >= 0),
  CONSTRAINT chk_ir_gracia CHECK (dias_gracia >= 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Prorrateo reglas / factores por unidad
-- -----------------------------------------------------------------------------
CREATE TABLE prorrateo_regla (
  id_prorrateo BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  id_concepto_cargo TINYINT NOT NULL,
  tipo ENUM('ordinario','extra','especial') NOT NULL DEFAULT 'ordinario',
  criterio ENUM('coef_prop','por_m2','igualitario','por_tipo','monto_fijo') NOT NULL,
  monto_total DECIMAL(14,2) NULL,
  peso_vivienda DECIMAL(10,6) NULL,
  peso_bodega DECIMAL(10,6) NULL,
  peso_estacionamiento DECIMAL(10,6) NULL,
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NULL,
  descripcion VARCHAR(300) NULL,
  PRIMARY KEY (id_prorrateo),
  UNIQUE KEY uk_prorrateo_unico (id_condominio, id_concepto_cargo, vigente_desde, tipo),
  KEY ix_prr_rango (id_condominio, id_concepto_cargo, vigente_desde, vigente_hasta),
  CONSTRAINT fk_prr_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_prr_concepto FOREIGN KEY (id_concepto_cargo) REFERENCES cat_concepto_cargo(id_concepto_cargo) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_prr_monto CHECK (monto_total IS NULL OR monto_total >= 0)
) ENGINE=InnoDB;

CREATE TABLE prorrateo_factor_unidad (
  id_factor BIGINT NOT NULL AUTO_INCREMENT,
  id_prorrateo BIGINT NOT NULL,
  id_unidad BIGINT NOT NULL,
  factor DECIMAL(12,6) NOT NULL,
  PRIMARY KEY (id_factor),
  UNIQUE KEY uk_factor_unidad (id_prorrateo, id_unidad),
  KEY fk_pf_prr (id_prorrateo),
  KEY fk_pf_unidad (id_unidad),
  CONSTRAINT fk_pf_prr FOREIGN KEY (id_prorrateo) REFERENCES prorrateo_regla(id_prorrateo) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_pf_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT chk_pf_factor CHECK (factor >= 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Trabajadores / Contratos / Remuneraciones
-- -----------------------------------------------------------------------------
CREATE TABLE trabajador (
  id_trabajador BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  rut_base INT NOT NULL,
  rut_dv CHAR(1) NOT NULL,
  nombres VARCHAR(120) NOT NULL,
  apellidos VARCHAR(120) NOT NULL,
  cargo VARCHAR(80) NOT NULL,
  email VARCHAR(120) NULL,
  telefono VARCHAR(40) NULL,
  PRIMARY KEY (id_trabajador),
  UNIQUE KEY uk_trab_rut_condo (id_condominio, rut_base, rut_dv),
  CONSTRAINT fk_trab_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_trab_rut CHECK (rut_base > 0 AND rut_dv REGEXP '^[0-9Kk]$'),
  CONSTRAINT chk_trab_email CHECK (email IS NULL OR email REGEXP '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$')
) ENGINE=InnoDB;

CREATE TABLE trabajador_contrato (
  id_contrato BIGINT NOT NULL AUTO_INCREMENT,
  id_trabajador BIGINT NOT NULL,
  tipo_contrato VARCHAR(40) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_termino DATE NULL,
  sueldo_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  jornada VARCHAR(60) NULL,
  documento_url VARCHAR(500) NULL,
  PRIMARY KEY (id_contrato),
  KEY fk_tc_trab (id_trabajador),
  CONSTRAINT fk_tc_trab FOREIGN KEY (id_trabajador) REFERENCES trabajador(id_trabajador) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT chk_tc_sueldo CHECK (sueldo_base >= 0)
) ENGINE=InnoDB;

CREATE TABLE remuneracion (
  id_remuneracion BIGINT NOT NULL AUTO_INCREMENT,
  id_trabajador BIGINT NOT NULL,
  tipo ENUM('mensual','finiquito','bono','retroactivo','otro') NOT NULL DEFAULT 'mensual',
  periodo CHAR(6) NOT NULL,
  bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  imposiciones DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  liquido DECIMAL(12,2) NOT NULL,
  fecha_pago DATE NULL,
  id_metodo_pago TINYINT NULL,
  comprobante_url VARCHAR(500) NULL,
  observacion VARCHAR(300) NULL,
  PRIMARY KEY (id_remuneracion),
  UNIQUE KEY uk_remu_trab_periodo (id_trabajador, periodo, tipo),
  KEY fk_remu_trab (id_trabajador),
  KEY fk_remu_metodo (id_metodo_pago),
  CONSTRAINT fk_remu_trab FOREIGN KEY (id_trabajador) REFERENCES trabajador(id_trabajador) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_remu_metodo FOREIGN KEY (id_metodo_pago) REFERENCES cat_metodo_pago(id_metodo_pago) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT chk_remu_periodo CHECK (periodo REGEXP '^[0-9]{6}$'),
  CONSTRAINT chk_remu_montos CHECK (bruto >= 0 AND imposiciones >= 0 AND descuentos >= 0 AND liquido >= 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Cargos / Cobros / Pagos / Pasarela
-- -----------------------------------------------------------------------------
CREATE TABLE cargo_unidad (
  id_cargo_uni BIGINT NOT NULL AUTO_INCREMENT,
  id_unidad BIGINT NOT NULL,
  periodo CHAR(6) NOT NULL,
  id_concepto_cargo TINYINT NOT NULL,
  tipo ENUM('normal','extra','ajuste') NOT NULL DEFAULT 'normal',
  monto DECIMAL(12,2) NOT NULL,
  detalle VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cargo_uni),
  KEY ix_cargo_periodo_unidad (periodo, id_unidad),
  KEY fk_cargo_unidad (id_unidad),
  KEY fk_cargo_concepto (id_concepto_cargo),
  CONSTRAINT fk_cargo_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_cargo_concepto FOREIGN KEY (id_concepto_cargo) REFERENCES cat_concepto_cargo(id_concepto_cargo) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_cargouni_periodo CHECK (periodo REGEXP '^[0-9]{6}$'),
  CONSTRAINT chk_cargouni_monto CHECK (monto >= 0)
) ENGINE=InnoDB;

CREATE TABLE cargo_individual (
  id_cargo_indv BIGINT NOT NULL AUTO_INCREMENT,
  id_unidad BIGINT NOT NULL,
  periodo CHAR(6) NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  referencia VARCHAR(60) NULL,
  monto DECIMAL(12,2) NOT NULL,
  detalle VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cargo_indv),
  KEY ix_ci (id_unidad, periodo),
  CONSTRAINT fk_ci_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_ci_periodo CHECK (periodo REGEXP '^[0-9]{6}$'),
  CONSTRAINT chk_ci_monto CHECK (monto >= 0)
) ENGINE=InnoDB;

CREATE TABLE cobro (
  id_cobro BIGINT NOT NULL AUTO_INCREMENT,
  id_unidad BIGINT NOT NULL,
  periodo CHAR(6) NOT NULL,
  emitido_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_cobro_estado TINYINT NOT NULL,
  tipo ENUM('mensual','extraordinario','manual') NOT NULL DEFAULT 'mensual',
  id_prorrateo BIGINT NULL,
  total_cargos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_interes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_pagado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  observacion VARCHAR(300) NULL,
  PRIMARY KEY (id_cobro),
  UNIQUE KEY uk_cobro_unidad_periodo (id_unidad, periodo, tipo),
  KEY fk_cobro_unidad (id_unidad),
  KEY fk_cobro_estado (id_cobro_estado),
  KEY fk_cobro_prr (id_prorrateo),
  KEY ix_cobro_unidad_periodo_estado (id_unidad, periodo, id_cobro_estado),
  CONSTRAINT fk_cobro_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_cobro_estado FOREIGN KEY (id_cobro_estado) REFERENCES cat_cobro_estado(id_cobro_estado) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_cobro_prr FOREIGN KEY (id_prorrateo) REFERENCES prorrateo_regla(id_prorrateo) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT chk_cobro_periodo CHECK (periodo REGEXP '^[0-9]{6}$'),
  CONSTRAINT chk_cobro_montos CHECK (total_cargos >= 0 AND total_descuentos >= 0 AND total_interes >= 0 AND total_pagado >= 0 AND saldo >= 0)
) ENGINE=InnoDB;

CREATE TABLE cobro_detalle (
  id_cobro_det BIGINT NOT NULL AUTO_INCREMENT,
  id_cobro BIGINT NOT NULL,
  tipo ENUM('cargo_comun','cargo_individual','interes_mora','descuento','ajuste') NOT NULL,
  id_cargo_uni BIGINT NULL,
  id_cargo_indv BIGINT NULL,
  id_interes_regla BIGINT NULL,
  tasa_aplicada_pct DECIMAL(6,3) NULL,
  monto DECIMAL(12,2) NOT NULL,
  glosa VARCHAR(300) NULL,
  PRIMARY KEY (id_cobro_det),
  KEY fk_cd_cobro (id_cobro),
  KEY fk_cd_cu (id_cargo_uni),
  KEY fk_cd_ci (id_cargo_indv),
  KEY fk_cd_ir (id_interes_regla),
  CONSTRAINT fk_cd_cobro FOREIGN KEY (id_cobro) REFERENCES cobro(id_cobro) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_cd_cu FOREIGN KEY (id_cargo_uni) REFERENCES cargo_unidad(id_cargo_uni) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_cd_ci FOREIGN KEY (id_cargo_indv) REFERENCES cargo_individual(id_cargo_indv) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_cd_ir FOREIGN KEY (id_interes_regla) REFERENCES interes_regla(id_interes_regla) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT chk_cd_monto CHECK (monto >= 0)
) ENGINE=InnoDB;

CREATE TABLE pago (
  id_pago BIGINT NOT NULL AUTO_INCREMENT,
  id_unidad BIGINT NOT NULL,
  fecha_pago DATETIME NOT NULL,
  periodo CHAR(6) NULL,
  tipo ENUM('normal','anticipo','ajuste') NOT NULL DEFAULT 'normal',
  monto DECIMAL(12,2) NOT NULL,
  id_metodo_pago TINYINT NOT NULL,
  ref_externa VARCHAR(120) NULL,
  observacion VARCHAR(300) NULL,
  PRIMARY KEY (id_pago),
  KEY ix_pago_unidad_periodo (id_unidad, periodo),
  KEY ix_pago_unidad_fecha (id_unidad, fecha_pago),
  CONSTRAINT fk_pago_unidad FOREIGN KEY (id_unidad) REFERENCES unidad(id_unidad) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_pago_metodo FOREIGN KEY (id_metodo_pago) REFERENCES cat_metodo_pago(id_metodo_pago) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_pago_monto CHECK (monto >= 0),
  CONSTRAINT chk_pago_periodo CHECK (periodo IS NULL OR periodo REGEXP '^[0-9]{6}$')
) ENGINE=InnoDB;

CREATE TABLE comprobante_pago (
  id_compr_pago BIGINT NOT NULL AUTO_INCREMENT,
  id_pago BIGINT NOT NULL,
  folio VARCHAR(40) NOT NULL,
  url_pdf VARCHAR(500) NULL,
  emitido_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_compr_pago),
  UNIQUE KEY uk_comp_folio (folio),
  UNIQUE KEY uk_comp_pago (id_pago),
  CONSTRAINT fk_comp_pago FOREIGN KEY (id_pago) REFERENCES pago(id_pago) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pago_aplicacion (
  id_pago_aplic BIGINT NOT NULL AUTO_INCREMENT,
  id_pago BIGINT NOT NULL,
  id_cobro BIGINT NOT NULL,
  monto_aplicado DECIMAL(12,2) NOT NULL,
  aplicado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pago_aplic),
  KEY fk_pa_pago (id_pago),
  KEY fk_pa_cobro (id_cobro),
  UNIQUE KEY uk_pa_pago_cobro (id_pago, id_cobro),
  CONSTRAINT fk_pa_pago FOREIGN KEY (id_pago) REFERENCES pago(id_pago) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_pa_cobro FOREIGN KEY (id_cobro) REFERENCES cobro(id_cobro) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT chk_pa_monto CHECK (monto_aplicado >= 0)
) ENGINE=InnoDB;

CREATE TABLE pasarela_tx (
  id_pasarela_tx BIGINT NOT NULL AUTO_INCREMENT,
  id_pago BIGINT NOT NULL,
  id_pasarela TINYINT NOT NULL,
  id_estado_tx TINYINT NOT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pasarela_tx),
  KEY fk_tx_pago (id_pago),
  KEY fk_tx_pasarela (id_pasarela),
  KEY fk_tx_estado (id_estado_tx),
  CONSTRAINT fk_tx_pago FOREIGN KEY (id_pago) REFERENCES pago(id_pago) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_tx_pasarela FOREIGN KEY (id_pasarela) REFERENCES cat_pasarela(id_pasarela) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_tx_estado FOREIGN KEY (id_estado_tx) REFERENCES cat_estado_tx(id_estado_tx) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Gastos / Fondo de reserva / Libro mayor / Auditoría / Resumen mensual
-- -----------------------------------------------------------------------------
CREATE TABLE gasto (
  id_gasto BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  periodo CHAR(6) NOT NULL,
  id_gasto_categ SMALLINT NOT NULL,
  id_proveedor BIGINT NULL,
  id_doc_tipo TINYINT NULL,
  documento_folio VARCHAR(40) NULL,
  fecha_emision DATE NULL,
  fecha_venc DATE NULL,
  neto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  iva DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) AS (ROUND(neto + iva, 2)) STORED,
  descripcion VARCHAR(300) NULL,
  evidencia_url VARCHAR(500) NULL,
  PRIMARY KEY (id_gasto),
  KEY ix_gasto_periodo (id_condominio, periodo),
  KEY fk_gasto_condo (id_condominio),
  KEY fk_gasto_cat (id_gasto_categ),
  KEY fk_gasto_prov (id_proveedor),
  KEY fk_gasto_doctipo (id_doc_tipo),
  CONSTRAINT fk_gasto_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_gasto_cat FOREIGN KEY (id_gasto_categ) REFERENCES gasto_categoria(id_gasto_categ) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_gasto_prov FOREIGN KEY (id_proveedor) REFERENCES proveedor(id_proveedor) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_gasto_doctipo FOREIGN KEY (id_doc_tipo) REFERENCES cat_doc_tipo(id_doc_tipo) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT chk_gasto_periodo CHECK (periodo REGEXP '^[0-9]{6}$'),
  CONSTRAINT chk_gasto_montos CHECK (neto >= 0 AND iva >= 0)
) ENGINE=InnoDB;

CREATE TABLE fondo_reserva_mov (
  id_fondo_reserva BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  fecha DATETIME NOT NULL,
  tipo VARCHAR(10) NOT NULL, -- 'abono'/'cargo'
  periodo CHAR(6) NULL,
  monto DECIMAL(12,2) NOT NULL,
  glosa VARCHAR(300) NULL,
  PRIMARY KEY (id_fondo_reserva),
  KEY ix_fr (id_condominio, fecha),
  CONSTRAINT fk_fr_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_fr_monto CHECK (monto >= 0),
  CONSTRAINT chk_fr_periodo CHECK (periodo IS NULL OR periodo REGEXP '^[0-9]{6}$')
) ENGINE=InnoDB;

CREATE TABLE libro_movimiento (
  id_libro_mov BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  fecha DATETIME NOT NULL,
  id_cta_contable SMALLINT NOT NULL,
  debe DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  haber DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ref_tabla VARCHAR(40) NULL,
  ref_id BIGINT NULL,
  glosa VARCHAR(300) NULL,
  PRIMARY KEY (id_libro_mov),
  KEY ix_mayor (id_condominio, fecha, id_cta_contable),
  CONSTRAINT fk_mayor_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_mayor_cuenta FOREIGN KEY (id_cta_contable) REFERENCES cuenta_contable(id_cta_contable) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE auditoria (
  id_auditoria BIGINT NOT NULL AUTO_INCREMENT,
  entidad VARCHAR(40) NOT NULL,
  entidad_id BIGINT NOT NULL,
  accion VARCHAR(10) NOT NULL, -- 'CREAR','EDITAR','ELIMINAR', etc.
  id_usuario BIGINT NULL,
  usuario_email VARCHAR(120) NULL,
  detalle JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_auditoria),
  KEY fk_audit_usuario (id_usuario),
  CONSTRAINT fk_audit_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE resumen_mensual (
  id_resumen BIGINT NOT NULL AUTO_INCREMENT,
  id_condominio BIGINT NOT NULL,
  periodo CHAR(6) NOT NULL,
  total_gastos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_cargos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_interes DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_descuentos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_pagado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  saldo_por_cobrar DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  generado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_resumen),
  UNIQUE KEY uk_resumen_condo_periodo (id_condominio, periodo),
  KEY fk_resumen_condo (id_condominio),
  CONSTRAINT fk_resumen_condo FOREIGN KEY (id_condominio) REFERENCES condominio(id_condominio) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_resumen_periodo CHECK (periodo REGEXP '^[0-9]{6}$')
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TRIGGERS de validación de RUT (usando fn_valida_dv)
-- -----------------------------------------------------------------------------

-- CONDOMINIO: opcional (permite NULL, pero si viene valor, valida)
CREATE TRIGGER trg_condominio_bi_rut
BEFORE INSERT ON condominio
FOR EACH ROW
BEGIN
  IF NOT (NEW.rut_base IS NULL AND NEW.rut_dv IS NULL) THEN
    IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT condominio inválido';
    END IF;
  END IF;
END;

CREATE TRIGGER trg_condominio_bu_rut
BEFORE UPDATE ON condominio
FOR EACH ROW
BEGIN
  IF NOT (NEW.rut_base IS NULL AND NEW.rut_dv IS NULL) THEN
    IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT condominio inválido';
    END IF;
  END IF;
END;

-- USUARIO: obligatorio
CREATE TRIGGER trg_usuario_bi_rut
BEFORE INSERT ON usuario
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT usuario inválido';
  END IF;
END;

CREATE TRIGGER trg_usuario_bu_rut
BEFORE UPDATE ON usuario
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT usuario inválido';
  END IF;
END;

-- PROVEEDOR: obligatorio
CREATE TRIGGER trg_proveedor_bi_rut
BEFORE INSERT ON proveedor
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT proveedor inválido';
  END IF;
END;

CREATE TRIGGER trg_proveedor_bu_rut
BEFORE UPDATE ON proveedor
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT proveedor inválido';
  END IF;
END;

-- TRABAJADOR: obligatorio
CREATE TRIGGER trg_trabajador_bi_rut
BEFORE INSERT ON trabajador
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT trabajador inválido';
  END IF;
END;

CREATE TRIGGER trg_trabajador_bu_rut
BEFORE UPDATE ON trabajador
FOR EACH ROW
BEGIN
  IF fn_valida_dv(NEW.rut_base, NEW.rut_dv) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUT trabajador inválido';
  END IF;
END;

-- =============================================================================
-- Fin
-- =============================================================================
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
