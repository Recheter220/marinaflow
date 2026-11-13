<?php

namespace Database\Factories;

use App\Models\Servico;
use App\Models\ServicoFoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Só o registro — o arquivo no disco fica por conta de quem precisar dele.
 *
 * @extends Factory<ServicoFoto>
 */
class ServicoFotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servico_id' => Servico::factory(),
            'disk' => config('filesystems.fotos_disk'),
            'path' => fn (array $attributes): string => 'servicos/'.$attributes['servico_id'].'/'.fake()->uuid().'.jpg',
            'nome_original' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'tamanho_bytes' => fake()->numberBetween(50_000, 3_000_000),
            'ordem' => 0,
            'uploaded_by_user_id' => null,
        ];
    }
}
