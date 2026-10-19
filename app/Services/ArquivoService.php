<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Único ponto da aplicação que fala com o disco de fotos — controllers gravam,
 * servem e removem arquivos por aqui, nunca pelo `Storage` direto (RF-03.4).
 * O disco vem de `filesystems.fotos_disk`: `fotos` (R2) ou `fotos-local`.
 *
 * Os arquivos são privados: leitura só por URL temporária assinada (RF-03.3).
 */
class ArquivoService
{
    /**
     * Grava o arquivo com nome gerado e devolve o caminho relativo ao disco.
     */
    public function guardar(UploadedFile $arquivo, string $pasta): string
    {
        return $this->disco()->putFile($pasta, $arquivo);
    }

    public function urlTemporaria(string $path, int $minutos = 15): string
    {
        return $this->disco()->temporaryUrl($path, now()->addMinutes($minutos));
    }

    public function remover(string $path): void
    {
        $this->disco()->delete($path);
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('filesystems.fotos_disk'));
    }
}
