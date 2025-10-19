<?php

namespace Database\Factories;

use App\Models\GastoCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

class GastoCategoriaFactory extends Factory
{
    protected $model = GastoCategoria::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word,
        ];
    }
}