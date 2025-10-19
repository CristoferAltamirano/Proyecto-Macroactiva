<?php

namespace Database\Factories;

use App\Models\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadFactory extends Factory
{
    protected $model = Unidad::class;

    public function definition(): array
    {
        return [
            'id_grupo' => \App\Models\Grupo::factory(),
            'codigo' => 'Depto '.$this->faker->numberBetween(101,999),
        ];
    }
}