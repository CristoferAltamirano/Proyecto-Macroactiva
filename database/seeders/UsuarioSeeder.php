<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'tipo_usuario'=>'super_admin','rut_base'=>11111111,'rut_dv'=>'1',
                'nombres'=>'Super','apellidos'=>'Admin','email'=>'super@macroactiva.test',
                'pass_hash'=>Hash::make('super1234'),'activo'=>1
            ],
            [
                'tipo_usuario'=>'admin','rut_base'=>22222222,'rut_dv'=>'2',
                'nombres'=>'Admin','apellidos'=>'Condominio','email'=>'admin@macroactiva.test',
                'pass_hash'=>Hash::make('admin1234'),'activo'=>1
            ],
        ];
        foreach ($users as $u) {
            DB::table('usuario')->updateOrInsert(['email'=>$u['email']], $u);
        }
    }
}
