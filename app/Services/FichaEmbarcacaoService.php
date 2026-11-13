<?php

namespace App\Services;

use App\Models\Embarcacao;
use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Models\User;
use App\Repositories\EmbarcacaoRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class FichaEmbarcacaoService
{
    public function __construct(private readonly EmbarcacaoRepository $embarcacaoRepository) {}

    public function buscar(int $id, User $usuario): Embarcacao
    {
        return Embarcacao::query()
            ->forUser($usuario)
            ->findOrFail($id);
    }

    /**
     * @return array{
     *     id: int,
     *     ulid: string,
     *     nome: string,
     *     identificacao: string,
     *     modelo: ?string,
     *     tipo: ?string,
     *     comprimento: ?float,
     *     ano_fabricacao: ?int,
     *     cliente_responsavel: ?string,
     *     status: string
     * }
     */
    public function dados(Embarcacao $embarcacao): array
    {
        return [
            'id' => $embarcacao->id,
            'ulid' => $embarcacao->ulid,
            'nome' => $embarcacao->nome,
            'identificacao' => $embarcacao->identificacao,
            'modelo' => $embarcacao->modelo,
            'tipo' => $embarcacao->tipo,
            'comprimento' => $embarcacao->comprimento,
            'ano_fabricacao' => $embarcacao->ano_fabricacao,
            'cliente_responsavel' => $embarcacao->cliente_responsavel,
            'status' => $embarcacao->status,
        ];
    }

    /**
     * @return array{
     *     total_servicos: int,
     *     ultimo_servico: ?string,
     *     servicos_em_execucao: int,
     *     custo_pecas: null,
     *     tipos_frequentes: array<int, array{nome: string, total: int}>
     * }
     */
    public function resumo(Embarcacao $embarcacao): array
    {
        $base = $embarcacao->servicos();

        $ultimoServico = $base->max('data_execucao');

        $tiposFrequentes = $this->embarcacaoRepository->tiposFrequentes($embarcacao);

        return [
            'total_servicos' => $base->count(),
            'ultimo_servico' => $ultimoServico ? Carbon::parse($ultimoServico)->toDateString() : null,
            'servicos_em_execucao' => $base->where('status', Servico::STATUS_EM_EXECUCAO)->count(),
            'custo_pecas' => null,
            'tipos_frequentes' => $tiposFrequentes,
        ];
    }

    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function linhaDoTempo(Embarcacao $embarcacao, int $pagina = 1): array
    {
        $paginador = $embarcacao->servicos()
            ->with([
                'tipos:id,nome',
                'fotos:id,servico_id,disk,path,nome_original,ordem',
                'funcionario:id,nome',
            ])
            ->orderByDesc('data_execucao')
            ->orderByDesc('id')
            ->paginate(perPage: 20, page: $pagina);

        $data = collect($paginador->items())
            ->map(fn (Servico $s): array => [
                'id' => $s->id,
                'data_execucao' => $s->data_execucao?->toDateString(),
                'status' => $s->status,
                'status_rotulo' => Servico::STATUS_ROTULOS[$s->status] ?? $s->status,
                'observacao' => $s->observacao,
                'funcionario' => $s->funcionario ? [
                    'id' => $s->funcionario->id,
                    'nome' => $s->funcionario->nome,
                ] : null,
                'tipos' => $s->tipos
                    ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->map(fn ($t): array => ['id' => $t->id, 'nome' => $t->nome])
                    ->all(),
                'fotos_count' => $s->fotos->count(),
            ])
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     servico_id: int,
     *     disk: string,
     *     path: string,
     *     nome_original: string,
     *     servico_data: ?string,
     *     url: string
     * }>
     */
    public function galeria(Embarcacao $embarcacao): array
    {
        return ServicoFoto::query()
            ->join('servicos as s', 's.id', '=', 'servico_fotos.servico_id')
            ->where('s.embarcacao_id', $embarcacao->id)
            ->orderByDesc('s.data_execucao')
            ->orderByDesc('servico_fotos.id')
            ->get([
                'servico_fotos.id',
                'servico_fotos.servico_id',
                'servico_fotos.disk',
                'servico_fotos.path',
                'servico_fotos.nome_original',
                's.data_execucao as servico_data',
            ])
            ->map(fn (ServicoFoto $foto): array => [
                'id' => $foto->id,
                'servico_id' => $foto->servico_id,
                'disk' => $foto->disk,
                'path' => $foto->path,
                'nome_original' => $foto->nome_original,
                'servico_data' => $foto->servico_data
                    ? Carbon::parse($foto->servico_data)->toDateString()
                    : null,
                'url' => Storage::disk($foto->disk)
                    ->temporaryUrl($foto->path, now()->addMinutes(30)),
            ])
            ->all();
    }
}
