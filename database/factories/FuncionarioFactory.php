<?php

namespace Database\Factories;

use App\Models\Funcionario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Funcionario>
 */
class FuncionarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'cargo' => fake()->randomElement(['Mecânico', 'Eletricista', 'Pintor']),
            'telefone' => fake()->numerify('###########'),
            'ativo' => true,
            'cep' => fake()->numerify('########'),
            'logradouro' => fake()->streetName(),
            'numero' => fake()->buildingNumber(),
            'complemento' => null,
            'bairro' => fake()->randomElement(['Centro', 'Jardim das Flores', 'Vila Nova']),
            'cidade' => fake()->city(),
            'uf' => fake()->randomElement(['SP', 'RJ', 'MG']),
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ativo' => false,
        ]);
    }
}
