<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Importante importar Hash

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usamos User::create para crear el usuario de forma segura.
        // Esto utiliza el $fillable que ya corregimos en el modelo User.
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'tipo_usuario' => 'super-admin',
            'id_unidad' => null,
        ]);
    }
}