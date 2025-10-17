<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBasicoSeeder extends Seeder
{
    public function run(): void
    {
        $condoId = DB::table('condominio')->where('nombre','Condominio Demo')->value('id_condominio')
            ?? DB::table('condominio')->insertGetId(['nombre'=>'Condominio Demo','email_contacto'=>'admin@condodemo.cl']);
        $grupoId = DB::table('grupo')->where('id_condominio',$condoId)->where('nombre','Torre A')->value('id_grupo')
            ?? DB::table('grupo')->insertGetId(['id_condominio'=>$condoId,'nombre'=>'Torre A','tipo'=>'torre']);
        $unidadId = DB::table('unidad')->where('id_grupo',$grupoId)->where('codigo','101')->value('id_unidad')
            ?? DB::table('unidad')->insertGetId([
                'id_grupo'=>$grupoId,'codigo'=>'101','direccion'=>'Torre A, Depto 101',
                'id_unidad_tipo'=>DB::table('cat_unidad_tipo')->where('codigo','vivienda')->value('id_unidad_tipo'),
                'id_viv_subtipo'=>DB::table('cat_vivienda_subtipo')->where('codigo','departamento')->value('id_viv_subtipo'),
                'id_segmento'=>DB::table('cat_segmento')->where('codigo','residencial')->value('id_segmento'),
                'anexo_incluido'=>0,'anexo_cobrable'=>0,'coef_prop'=>0.050,'habitable'=>1
            ]);

        // cargo de ejemplo
        if (!DB::table('cargo_unidad')->where('id_unidad',$unidadId)->where('periodo','202509')->exists()) {
            DB::table('cargo_unidad')->insert([
                'id_unidad'=>$unidadId,'periodo'=>'202509','id_concepto_cargo'=>1,
                'tipo'=>'normal','monto'=>50000,'detalle'=>'Gasto común septiembre'
            ]);
        }
        echo "Demo: id_condominio=$condoId, id_grupo=$grupoId, id_unidad=$unidadId\n";
    }
}
