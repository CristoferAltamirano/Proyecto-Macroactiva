<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cobro>
 */
class CobroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidad_id' => \App\Models\Unidad::factory(),
            'periodo' => $this->faker->date(),
            'monto_gasto_comun' => $this->faker->numberBetween(1000, 100000),
            'monto_fondo_reserva' => $this->faker->numberBetween(1000, 100000),
            'monto_multas' => $this->faker->numberBetween(0, 10000),
            'monto_total' => $this->faker->numberBetween(1000, 100000),
            'estado' => 'pendiente',
        ];
    }
}