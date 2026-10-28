<?php

namespace Database\Seeders;

use App\Models\Servico;
use App\Services\ArquivoService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Uma foto gerada por serviço que ainda não tem nenhuma, gravada de verdade no
 * disco de fotos para a miniatura abrir. Fora do `DatabaseSeeder` porque ele
 * não cria serviços: rodar com `db:seed --class=ServicoFotoSeeder`.
 */
class ServicoFotoSeeder extends Seeder
{
    public function run(ArquivoService $arquivos): void
    {
        Servico::query()->doesntHave('fotos')->each(function (Servico $servico) use ($arquivos): void {
            $arquivo = UploadedFile::fake()->image('casco.jpg', 1280, 960);

            $servico->fotos()->create([
                'disk' => config('filesystems.fotos_disk'),
                'path' => $arquivos->guardar($arquivo, "servicos/{$servico->id}"),
                'nome_original' => $arquivo->getClientOriginalName(),
                'mime_type' => 'image/jpeg',
                'tamanho_bytes' => $arquivo->getSize(),
            ]);
        });
    }
}
