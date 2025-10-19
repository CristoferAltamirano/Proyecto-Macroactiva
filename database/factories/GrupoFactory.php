<?php

namespace Database\Factories;

use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'id_condominio' => \App\Models\Condominio::factory(),
            'nombre' => 'Torre '.$this->faker->numberBetween(1, 4),
            'tipo' => 'torre',
        ];
    }
}