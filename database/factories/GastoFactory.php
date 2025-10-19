<?php

namespace Database\Factories;

use App\Models\Gasto;
use Illuminate\Database\Eloquent\Factories\Factory;

class GastoFactory extends Factory
{
    protected $model = Gasto::class;

    public function definition(): array
    {
        return [
            'condominio_id' => \App\Models\Condominio::factory(),
            'periodo' => $this->faker->date('Ym'),
            'id_gasto_categ' => \App\Models\GastoCategoria::factory(),
            'neto' => $this->faker->numberBetween(1000, 100000),
            'iva' => $this->faker->numberBetween(190, 19000),
            'descripcion' => $this->faker->text,
            'fecha_emision' => $this->faker->dateTime(),
        ];
    }
}