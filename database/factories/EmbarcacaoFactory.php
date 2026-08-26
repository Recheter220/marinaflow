<?php

namespace Database\Factories;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Embarcacao>
 */
class EmbarcacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->words(2, true),
            'identificacao' => fake()->unique()->bothify('BR-####-???'),
            'modelo' => fake()->word(),
            'tipo' => fake()->randomElement(['lancha', 'veleiro', 'iate', 'jet_ski']),
            'comprimento' => fake()->randomFloat(2, 3, 30),
            'ano_fabricacao' => fake()->numberBetween(1990, 2025),
            'cliente_responsavel' => fake()->name(),
            'status' => 'ativa',
            'funcionario_id' => null,
        ];
    }

    /** Vincula a embarcação a um funcionário — a condição de visibilidade do papel `funcionario`. */
    public function doFuncionario(Funcionario $funcionario): static
    {
        return $this->state(fn (array $attributes): array => [
            'funcionario_id' => $funcionario->id,
        ]);
    }
}
