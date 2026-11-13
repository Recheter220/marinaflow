<?php

namespace Database\Factories;

use App\Models\TipoServico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoServico>
 */
class TipoServicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->words(2, true),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ativo' => false,
        ]);
    }
}
