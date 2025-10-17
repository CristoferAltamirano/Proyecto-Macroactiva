<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $periodoActual = now()->format('Ym');
            $periodoPrevio = now()->copy()->subMonth()->format('Ym');

            // ===== Helpers para IDs de catálogos =====
            $idSegResid = $this->catId('cat_segmento','id_segmento','residencial');
            $idTipoViv  = $this->catId('cat_unidad_tipo','id_unidad_tipo','vivienda');
            $idVivDepto = $this->catId('cat_vivienda_subtipo','id_viv_subtipo','departamento');
            $idMetodoTransfer = $this->catMetodo('transferencia'); // fallback a 1 si no existe
            $idDocFactura = $this->catDoc('factura');
            $idConceptoViv = $this->catConcepto('vivienda');

            // ===== Condominio =====
            $idCondo = DB::table('condominio')->where('nombre','Edificio Demo')->value('id_condominio');
            if (!$idCondo) {
                $idCondo = DB::table('condominio')->insertGetId([
                    'nombre' => 'Edificio Demo',
                    'rut_base' => null, 'rut_dv' => null,
                    'direccion' => 'Avenida Siempre Viva 123',
                    'comuna' => 'Santiago', 'region' => 'RM',
                    'email_contacto' => 'admin@demo.cl',
                    'telefono' => '+56 2 1234 5678',
                ]);
            }

            // Param reglamento + interes
            if (!DB::table('param_reglamento')->where('id_condominio',$idCondo)->exists()) {
                DB::table('param_reglamento')->insert([
                    'id_condominio'=>$idCondo,
                    'recargo_fondo_reserva_pct'=>5.00,
                    'interes_mora_anual_pct'=>18.000,
                    'dias_gracia'=>5,
                    'multa_morosidad_fija'=>null
                ]);
            }
            if (!DB::table('interes_regla')->where('id_condominio',$idCondo)->exists()) {
                DB::table('interes_regla')->insert([
                    'id_condominio'=>$idCondo,
                    'id_segmento'=>$idSegResid,
                    'vigente_desde'=>now()->firstOfYear()->toDateString(),
                    'vigente_hasta'=>null,
                    'tasa_anual_pct'=>18.000,
                    'dias_gracia'=>5,
                    'fuente_url'=>null,'comentario'=>'Demo'
                ]);
            }

            // ===== Grupos =====
            $idGrupoA = $this->ensureGrupo($idCondo,'Torre A','torre');
            $idGrupoB = $this->ensureGrupo($idCondo,'Torre B','torre');

            // ===== Unidades =====
            $unidades = [
                ['grupo'=>$idGrupoA,'codigo'=>'A-101','coef'=>0.065],
                ['grupo'=>$idGrupoA,'codigo'=>'A-102','coef'=>0.055],
                ['grupo'=>$idGrupoA,'codigo'=>'A-201','coef'=>0.070],
                ['grupo'=>$idGrupoB,'codigo'=>'B-101','coef'=>0.060],
                ['grupo'=>$idGrupoB,'codigo'=>'B-102','coef'=>0.050],
                ['grupo'=>$idGrupoB,'codigo'=>'B-201','coef'=>0.060],
            ];
            $unidadIds = [];
            foreach ($unidades as $u) {
                $idU = DB::table('unidad')->where('id_grupo',$u['grupo'])->where('codigo',$u['codigo'])->value('id_unidad');
                if (!$idU) {
                    $idU = DB::table('unidad')->insertGetId([
                        'id_grupo'=>$u['grupo'],
                        'codigo'=>$u['codigo'],
                        'direccion'=>null,
                        'id_unidad_tipo'=>$idTipoViv,
                        'id_viv_subtipo'=>$idVivDepto,
                        'id_segmento'=>$idSegResid,
                        'anexo_incluido'=>0,'anexo_cobrable'=>0,
                        'rol_sii'=>null,'metros2'=>null,
                        'coef_prop'=>$u['coef'],
                        'habitable'=>1
                    ]);
                }
                $unidadIds[$u['codigo']] = $idU;
            }

            // ===== Usuarios =====
            // RUT helpers
            $mkUser = function(string $tipo,string $nombres,string $apellidos,string $email,int $rutBase){
                $dv = $this->dv($rutBase);
                $id = DB::table('usuario')->where('email',$email)->value('id_usuario');
                if(!$id){
                    $id = DB::table('usuario')->insertGetId([
                        'tipo_usuario'=>$tipo,
                        'rut_base'=>$rutBase,'rut_dv'=>$dv,
                        'nombres'=>$nombres,'apellidos'=>$apellidos,
                        'email'=>$email,'telefono'=>null,'direccion'=>null,
                        'pass_hash'=>Hash::make('secret123'),
                        'activo'=>1,'creado_at'=>now(),
                    ]);
                }
                return $id;
            };

            $idSuper = $mkUser('super_admin','Super','Admin','super@demo.cl', 9999999);
            $idAdmin = $mkUser('admin','Admin','Condo','admin@demo.cl', 8888888);
            $idProp1 = $mkUser('copropietario','Ana','Propietaria','ana@demo.cl', 12345678);
            $idProp2 = $mkUser('copropietario','Ben','Propietario','ben@demo.cl', 87654321);
            $idRes1  = $mkUser('residente','Carla','Residente','carla@demo.cl', 11223344);
            $idRes2  = $mkUser('residente','Diego','Residente','diego@demo.cl', 44332211);

            // Admin asignado al condominio
            if (!DB::table('usuario_admin_condo')->where('id_usuario',$idAdmin)->where('id_condominio',$idCondo)->exists()){
                DB::table('usuario_admin_condo')->insert(['id_usuario'=>$idAdmin,'id_condominio'=>$idCondo]);
            }

            // ===== Copropietarios / Residencias =====
            $since = now()->copy()->firstOfYear()->toDateString();
            $this->ensureCoprop($idProp1, $unidadIds['A-101'], 100.000, $since);
            $this->ensureCoprop($idProp1, $unidadIds['A-102'], 100.000, $since);
            $this->ensureCoprop($idProp2, $unidadIds['B-101'], 100.000, $since);
            $this->ensureCoprop($idProp2, $unidadIds['B-102'], 100.000, $since);

            $this->ensureResid($idRes1, $unidadIds['A-101'], 'arrendatario', $since);
            $this->ensureResid($idRes2, $unidadIds['B-101'], 'arrendatario', $since);

            // ===== Prorrateo Regla (coef_prop) + factores =====
            $idRegla = DB::table('prorrateo_regla')
                ->where('id_condominio',$idCondo)
                ->where('id_concepto_cargo',$idConceptoViv)
                ->where('vigente_desde', now()->firstOfMonth()->toDateString())
                ->value('id_prorrateo');

            if (!$idRegla) {
                $idRegla = DB::table('prorrateo_regla')->insertGetId([
                    'id_condominio'=>$idCondo,
                    'id_concepto_cargo'=>$idConceptoViv,
                    'tipo'=>'ordinario',
                    'criterio'=>'coef_prop',
                    'monto_total'=>600000.00, // total a prorratear (referencial)
                    'peso_vivienda'=>null,'peso_bodega'=>null,'peso_estacionamiento'=>null,
                    'vigente_desde'=>now()->firstOfMonth()->toDateString(),
                    'vigente_hasta'=>null,
                    'descripcion'=>'Gastos comunes ordinarios'
                ]);
            }
            foreach ($unidadIds as $codigo=>$idU) {
                if (!DB::table('prorrateo_factor_unidad')->where('id_prorrateo',$idRegla)->where('id_unidad',$idU)->exists()) {
                    $coef = DB::table('unidad')->where('id_unidad',$idU)->value('coef_prop');
                    DB::table('prorrateo_factor_unidad')->insert([
                        'id_prorrateo'=>$idRegla, 'id_unidad'=>$idU, 'factor'=>$coef
                    ]);
                }
            }

            // ===== Cargos (periodo actual y uno anterior para interés) =====
            // Monto proporcional simple: total 600.000 * coef
            $total = 600000.00;
            foreach ($unidadIds as $codigo=>$idU) {
                $coef = (float) DB::table('unidad')->where('id_unidad',$idU)->value('coef_prop');
                $monto = round($total * $coef, 0);

                $this->ensureCargoUnidad($idU, $periodoActual, $idConceptoViv, 'normal', $monto, 'Gasto común '.$periodoActual);
            }
            // Cargos del periodo anterior sólo a algunas unidades para simular morosidad
            foreach (['A-101','B-101'] as $cod) {
                $idU = $unidadIds[$cod];
                $coef = (float) DB::table('unidad')->where('id_unidad',$idU)->value('coef_prop');
                $monto = round($total * $coef, 0);
                $this->ensureCargoUnidad($idU, $periodoPrevio, $idConceptoViv, 'normal', $monto, 'Gasto común '.$periodoPrevio);
            }

            // ===== Generar cobros desde cargos =====
            \App\Services\CobroService::generarDesdeCargos($periodoActual, $idCondo);
            \App\Services\CobroService::generarDesdeCargos($periodoPrevio, $idCondo);

            // Intereses (al corte actual) para morosos del periodo anterior
            \App\Services\CobroService::generarIntereses($periodoActual, $idCondo);

            // ===== Pagos de demo (uno total, uno parcial) =====
            // A-101 paga TODO del periodo actual
            $idUA101 = $unidadIds['A-101'];
            $cA101   = DB::table('cobro')->where('id_unidad',$idUA101)->where('periodo',$periodoActual)->first();
            if ($cA101) {
                $idPago = DB::table('pago')->insertGetId([
                    'id_unidad'=>$idUA101,
                    'fecha_pago'=>now()->toDateTimeString(),
                    'periodo'=>$periodoActual,
                    'tipo'=>'normal',
                    'monto'=>$cA101->saldo,
                    'id_metodo_pago'=>$idMetodoTransfer,
                    'ref_externa'=>Str::upper(Str::random(8)),
                    'observacion'=>'Pago demo',
                ]);
                DB::table('pago_aplicacion')->insert([
                    'id_pago'=>$idPago, 'id_cobro'=>$cA101->id_cobro,
                    'monto_aplicado'=>$cA101->saldo, 'aplicado_at'=>now()
                ]);
                \App\Services\CobroService::recalcularTotales((int)$cA101->id_cobro);
                \App\Services\ComprobantePagoService::emitir($idPago);
            }

            // B-101 paga PARCIAL del periodo actual
            $idUB101 = $unidadIds['B-101'];
            $cB101 = DB::table('cobro')->where('id_unidad',$idUB101)->where('periodo',$periodoActual)->first();
            if ($cB101) {
                $parcial = round($cB101->saldo * 0.5, 0);
                $idPago2 = DB::table('pago')->insertGetId([
                    'id_unidad'=>$idUB101,
                    'fecha_pago'=>now()->toDateTimeString(),
                    'periodo'=>$periodoActual,
                    'tipo'=>'normal',
                    'monto'=>$parcial,
                    'id_metodo_pago'=>$idMetodoTransfer,
                    'ref_externa'=>Str::upper(Str::random(8)),
                    'observacion'=>'Pago parcial demo',
                ]);
                DB::table('pago_aplicacion')->insert([
                    'id_pago'=>$idPago2, 'id_cobro'=>$cB101->id_cobro,
                    'monto_aplicado'=>$parcial, 'aplicado_at'=>now()
                ]);
                \App\Services\CobroService::recalcularTotales((int)$cB101->id_cobro);
                \App\Services\ComprobantePagoService::emitir($idPago2);
            }

            // ===== Proveedor + Gasto =====
            $idProv = DB::table('proveedor')->where('nombre','Proveedor Demo')->value('id_proveedor');
            if (!$idProv) {
                $idProv = DB::table('proveedor')->insertGetId([
                    'tipo'=>'empresa',
                    'rut_base'=>76451230,'rut_dv'=>$this->dv(76451230),
                    'nombre'=>'Proveedor Demo',
                    'giro'=>'Aseo y Mantención',
                    'email'=>'ventas@proveedor-demo.cl','telefono'=>'+56 2 9999 9999'
                ]);
            }
            $idCatGastoMant = DB::table('gasto_categoria')->where('nombre','Mantención')->value('id_gasto_categ');
            if (!$idCatGastoMant) {
                $idCatGastoMant = DB::table('gasto_categoria')->insertGetId(['nombre'=>'Mantención']);
            }
            if (!DB::table('gasto')->where('id_condominio',$idCondo)->where('periodo',$periodoActual)->exists()) {
                DB::table('gasto')->insert([
                    'id_condominio'=>$idCondo,
                    'periodo'=>$periodoActual,
                    'id_gasto_categ'=>$idCatGastoMant,
                    'id_proveedor'=>$idProv,
                    'id_doc_tipo'=>$idDocFactura,
                    'documento_folio'=>'F001-12345',
                    'fecha_emision'=>now()->toDateString(),
                    'fecha_venc'=>now()->addDays(15)->toDateString(),
                    'neto'=>500000.00, 'iva'=>95000.00,
                    'descripcion'=>'Servicio de aseo mensual',
                    'evidencia_url'=>null,
                ]);
            }

            DB::commit();
            $this->command?->info('DemoDataSeeder: OK');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function ensureGrupo(int $idCondo, string $nombre, string $tipo): int
    {
        $id = DB::table('grupo')->where('id_condominio',$idCondo)->where('nombre',$nombre)->value('id_grupo');
        if (!$id) {
            $id = DB::table('grupo')->insertGetId(['id_condominio'=>$idCondo,'nombre'=>$nombre,'tipo'=>$tipo]);
        }
        return $id;
    }

    private function ensureCoprop(int $idUsuario, int $idUnidad, float $porcentaje, string $desde): void
    {
        if (!DB::table('copropietario')->where('id_usuario',$idUsuario)->where('id_unidad',$idUnidad)->where('desde',$desde)->exists()){
            DB::table('copropietario')->insert(['id_usuario'=>$idUsuario,'id_unidad'=>$idUnidad,'porcentaje'=>$porcentaje,'desde'=>$desde,'hasta'=>null]);
        }
    }

    private function ensureResid(int $idUsuario, int $idUnidad, string $origen, string $desde): void
    {
        if (!DB::table('residencia')->where('id_usuario',$idUsuario)->where('id_unidad',$idUnidad)->where('desde',$desde)->exists()){
            DB::table('residencia')->insert(['id_usuario'=>$idUsuario,'id_unidad'=>$idUnidad,'origen'=>$origen,'desde'=>$desde,'hasta'=>null,'observacion'=>null]);
        }
    }

    private function ensureCargoUnidad(int $idUnidad, string $periodo, int $idConcepto, string $tipo, float $monto, ?string $detalle): void
    {
        if (!DB::table('cargo_unidad')->where('id_unidad',$idUnidad)->where('periodo',$periodo)->where('id_concepto_cargo',$idConcepto)->exists()){
            DB::table('cargo_unidad')->insert([
                'id_unidad'=>$idUnidad,
                'periodo'=>$periodo,
                'id_concepto_cargo'=>$idConcepto,
                'tipo'=>$tipo,
                'monto'=>$monto,
                'detalle'=>$detalle,
                'created_at'=>now(),
            ]);
        }
    }

    private function catId(string $table, string $idCol, string $codigo): int
    {
        $id = DB::table($table)->where('codigo',$codigo)->value($idCol);
        if (!$id) throw new \RuntimeException("No existe codigo '$codigo' en $table");
        return (int)$id;
    }
    private function catMetodo(string $codigo): int
    {
        return (int) (DB::table('cat_metodo_pago')->where('codigo',$codigo)->value('id_metodo_pago') ?? 1);
        // 1: asumimos existe al menos un método de pago por seeds
    }
    private function catDoc(string $codigo): ?int
    {
        return DB::table('cat_doc_tipo')->where('codigo',$codigo)->value('id_doc_tipo');
    }
    private function catConcepto(string $codigo): int
    {
        $id = DB::table('cat_concepto_cargo')->where('codigo',$codigo)->value('id_concepto_cargo');
        if (!$id) throw new \RuntimeException("No existe concepto '$codigo'");
        return (int)$id;
    }

    private function dv(int $rutBase): string
    {
        $s=0; $m=2; $x=$rutBase;
        if ($x<=0) return '0';
        while($x>0){ $s += ($x%10)*$m; $x = intdiv($x,10); $m = ($m==7)?2:$m+1; }
        $r = 11 - ($s % 11);
        return ($r==11)?'0' : (($r==10)?'K' : (string)$r);
    }
}
