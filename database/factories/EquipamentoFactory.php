<?php

namespace Database\Factories;

use App\Models\Equipamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipamento>
 */
class EquipamentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->words(2, true),
        ];
    }
}
