<?php

namespace Database\Factories;

use App\Models\CatMetodoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatMetodoPagoFactory extends Factory
{
    protected $model = CatMetodoPago::class;

    public function definition(): array
    {
        return [
            'codigo' => $this->faker->word,
            'nombre' => $this->faker->word,
        ];
    }
}