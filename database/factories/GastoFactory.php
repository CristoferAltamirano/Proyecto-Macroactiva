<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gasto>
 */
class GastoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => $this->faker->sentence,
            'monto' => $this->faker->numberBetween(1000, 100000),
            'tipo' => 'ordinario',
            'fecha_gasto' => $this->faker->date(),
            'periodo_gasto' => $this->faker->date(),
        ];
    }
}