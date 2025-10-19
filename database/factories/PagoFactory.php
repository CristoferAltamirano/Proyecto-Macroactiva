<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pago>
 */
class PagoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unidad = \App\Models\Unidad::factory()->create();
        $cobro = \App\Models\Cobro::factory()->create(['id_unidad' => $unidad->id_unidad]);

        return [
            'monto_pagado' => $this->faker->numberBetween(1000, 100000),
            'fecha_pago' => $this->faker->dateTime(),
            'cobro_id' => $cobro->id_cobro,
            'id_unidad' => $unidad->id_unidad,
        ];
    }

    public function forCobro(\App\Models\Cobro $cobro)
    {
        return $this->state(function (array $attributes) use ($cobro) {
            return [
                'cobro_id' => $cobro->id_cobro,
                'id_unidad' => $cobro->id_unidad,
            ];
        });
    }
}