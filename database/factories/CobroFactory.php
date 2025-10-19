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
            'id_unidad' => function () {
                return \App\Models\Unidad::factory()->create()->id_unidad;
            },
            'periodo' => $this->faker->date('Ym'),
            'id_cobro_estado' => 1, // 'emitido'
            'monto_total' => $this->faker->numberBetween(50000, 200000),
            'monto_fondo_reserva' => $this->faker->numberBetween(10000, 50000),
        ];
    }
}