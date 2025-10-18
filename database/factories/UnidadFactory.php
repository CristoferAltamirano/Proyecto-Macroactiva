<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unidad>
 */
class UnidadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => $this->faker->unique()->word,
            'residente' => $this->faker->name,
            'propietario' => $this->faker->name,
            'telefono' => $this->faker->phoneNumber,
            'prorrateo' => $this->faker->randomFloat(5, 0, 1),
            'email' => $this->faker->unique()->safeEmail(),
            'estado' => 'activo',
            'id_grupo' => \App\Models\Grupo::factory(),
            'residente_id' => null,
        ];
    }
}