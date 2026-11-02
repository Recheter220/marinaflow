<?php

namespace Database\Factories;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\TipoServico;
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
            'data_execucao' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'status' => Servico::STATUS_AGUARDANDO_VISTORIA,
            'observacao' => null,
        ];
    }

    /** Serviço sem item não existe; quem não escolher os tipos leva Limpeza e Motor. */
    public function configure(): static
    {
        return $this->afterCreating(function (Servico $servico): void {
            if ($servico->tipos()->exists()) {
                return;
            }

            $servico->tipos()->attach(
                collect(['Limpeza', 'Motor'])->map(fn (string $nome): int => TipoServico::query()->firstOrCreate(['nome' => $nome])->id),
            );
        });
    }

    /**
     * @param  array<int, string>  $nomes
     */
    public function comTipos(array $nomes): static
    {
        return $this->afterCreating(function (Servico $servico) use ($nomes): void {
            $servico->tipos()->sync(
                collect($nomes)->map(fn (string $nome): int => TipoServico::query()->firstOrCreate(['nome' => $nome])->id),
            );
        });
    }

    public function concluido(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Servico::STATUS_CONCLUIDO,
        ]);
    }
}
