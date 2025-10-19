<?php

namespace Database\Factories;

use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

class CondominioFactory extends Factory
{
    protected $model = Condominio::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Condominio '.$this->faker->company,
        ];
    }
}