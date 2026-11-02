<?php

namespace App\Services;

use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Fotos do serviço executado (MF-04). A autoria é a mesma de editar o serviço
 * (`ServicoService::autorizarAutoria`), o que já cobre a INV05.
 */
class ServicoFotoService
{
    public function __construct(
        private readonly ServicoService $servicos,
        private readonly ArquivoService $arquivos,
    ) {}

    /**
     * Grava os arquivos e cria os registros. Se o banco falhar, os arquivos já
     * gravados são removidos para não sobrar lixo no bucket.
     *
     * @param  array<int, UploadedFile>  $arquivos
     * @return Collection<int, ServicoFoto>
     */
    public function anexar(Servico $servico, array $arquivos, User $autor): Collection
    {
        $this->servicos->autorizarAutoria($servico, $autor);

        $gravados = [];

        try {
            return DB::transaction(function () use ($servico, $arquivos, $autor, &$gravados): Collection {
                $ordem = (int) $servico->fotos()->max('ordem');

                return collect($arquivos)->map(function (UploadedFile $arquivo) use ($servico, $autor, &$gravados, &$ordem): ServicoFoto {
                    $gravados[] = $path = $this->arquivos->guardar($arquivo, "servicos/{$servico->id}");

                    return $servico->fotos()->create([
                        'disk' => config('filesystems.fotos_disk'),
                        'path' => $path,
                        'nome_original' => $arquivo->getClientOriginalName(),
                        'mime_type' => $arquivo->getMimeType(),
                        'tamanho_bytes' => $arquivo->getSize(),
                        'ordem' => ++$ordem,
                        'uploaded_by_user_id' => $autor->id,
                    ]);
                });
            });
        } catch (Throwable $e) {
            array_walk($gravados, $this->arquivos->remover(...));

            throw $e;
        }
    }

    /**
     * O arquivo só sai do disco depois do commit — se o banco falhar, a foto
     * continua íntegra (RF-04.6).
     */
    public function remover(ServicoFoto $foto, User $autor): void
    {
        $this->servicos->autorizarAutoria($foto->servico, $autor);

        DB::transaction(fn () => $foto->delete());

        $this->arquivos->remover($foto->path);
    }

    /** Mesma visibilidade do histórico: funcionário só vê foto dos próprios serviços. */
    public function urlTemporaria(ServicoFoto $foto, User $leitor): string
    {
        if (! Servico::query()->forUser($leitor)->whereKey($foto->servico_id)->exists()) {
            throw new AccessDeniedHttpException('Acesso negado.');
        }

        return $this->arquivos->urlTemporaria($foto->path);
    }
}
