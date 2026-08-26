<?php

namespace Database\Factories;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servico>
 */
class ServicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'embarcacao_id' => Embarcacao::factory(),
            'funcionario_id' => Funcionario::factory(),
            'descricao' => 'Limpeza, Motor',
            'data_execucao' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'status' => Servico::STATUS_EM_EXECUCAO,
            'observacao' => null,
        ];
    }

    public function concluido(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Servico::STATUS_CONCLUIDO,
        ]);
    }
}
