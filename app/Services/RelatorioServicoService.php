<?php

namespace App\Services;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\TipoServico;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Relatório de serviços por período (MF-07). Uma consulta só, já recortada por
 * papel; o resumo é agregado em memória sobre as mesmas linhas que a listagem
 * mostra, então os dois nunca divergem.
 */
class RelatorioServicoService
{
    /** `app.timezone` é UTC; o papel impresso é lido na marina. */
    private const FUSO_EXIBICAO = 'America/Sao_Paulo';

    /** Ignora caixa e acento: "radio VHF" e "Rádio de som" lado a lado, como na tela. */
    private readonly \Collator $collator;

    public function __construct()
    {
        $this->collator = new \Collator('pt_BR');
        $this->collator->setStrength(\Collator::PRIMARY);
    }

    /**
     * @param  array{inicio: string, fim: string, embarcacao_id?: ?int, funcionario_id?: ?int}  $filtros
     * @return array{
     *     periodo: array{inicio: string, fim: string, por_extenso: string},
     *     filtros: array{embarcacao: ?string, funcionario: ?string},
     *     emissao: array{em: string, por: string},
     *     linhas: array<int, array{data: string, embarcacao: string, identificacao: string, funcionario: string, tipos: string, situacao: string, observacao: ?string}>,
     *     resumo: array{total: int, por_situacao: array<string, int>, por_tipo: array<string, int>, por_embarcacao: array<string, int>, por_funcionario: array<string, int>},
     *     arquivo: string,
     * }
     */
    public function montar(array $filtros, User $solicitante): array
    {
        $inicio = Carbon::parse($filtros['inicio'])->startOfDay();
        $fim = Carbon::parse($filtros['fim'])->startOfDay();
        $embarcacaoId = $filtros['embarcacao_id'] ?? null;
        // AC-07.2 — funcionário só vê o que é dele, diga a URL o que disser.
        $funcionarioId = $solicitante->isAdmin() ? ($filtros['funcionario_id'] ?? null) : $solicitante->funcionario_id;

        $servicos = Servico::query()
            ->forUser($solicitante)
            ->whereBetween('data_execucao', [$inicio->toDateString(), $fim->toDateString()])
            ->when($embarcacaoId, fn ($q) => $q->where('embarcacao_id', $embarcacaoId))
            ->when($funcionarioId, fn ($q) => $q->where('funcionario_id', $funcionarioId))
            ->with(['embarcacao:id,nome,identificacao', 'funcionario:id,nome', 'tipos:id,nome'])
            ->orderBy('data_execucao')
            ->orderBy('id')
            ->get();

        return [
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
                'por_extenso' => $this->periodoPorExtenso($inicio, $fim),
            ],
            'filtros' => [
                'embarcacao' => $embarcacaoId ? $this->rotuloEmbarcacao($embarcacaoId, $solicitante) : null,
                'funcionario' => $funcionarioId ? Funcionario::query()->whereKey($funcionarioId)->value('nome') : null,
            ],
            'emissao' => [
                'em' => now()->timezone(self::FUSO_EXIBICAO)->format('d/m/Y \à\s H:i'),
                'por' => $solicitante->name,
            ],
            'linhas' => $servicos->map(fn (Servico $s): array => [
                'data' => $s->data_execucao->format('d/m/Y'),
                'embarcacao' => $s->embarcacao->nome,
                'identificacao' => $s->embarcacao->identificacao,
                'funcionario' => $s->funcionario->nome,
                'tipos' => $this->nomesOrdenados($s->tipos)->implode(', '),
                'situacao' => Servico::STATUS_ROTULOS[$s->status] ?? $s->status,
                'observacao' => $s->observacao,
            ])->all(),
            'resumo' => $this->resumo($servicos),
            'arquivo' => $this->nomeDoArquivo($inicio, $fim),
        ];
    }

    /**
     * @param  Collection<int, Servico>  $servicos
     * @return array{total: int, por_situacao: array<string, int>, por_tipo: array<string, int>, por_embarcacao: array<string, int>, por_funcionario: array<string, int>}
     */
    private function resumo(Collection $servicos): array
    {
        // Toda situação aparece, mesmo zerada: o leitor confere que nada ficou de fora.
        $porSituacao = array_fill_keys(array_values(Servico::STATUS_ROTULOS), 0);

        foreach ($servicos->countBy('status') as $status => $quantidade) {
            $porSituacao[Servico::STATUS_ROTULOS[$status] ?? $status] = $quantidade;
        }

        return [
            'total' => $servicos->count(),
            'por_situacao' => $porSituacao,
            'por_tipo' => $this->contagemDecrescente($servicos->flatMap(fn (Servico $s) => $s->tipos->pluck('nome'))),
            'por_embarcacao' => $this->contagemDecrescente($servicos->map(fn (Servico $s): string => $s->embarcacao->nome)),
            'por_funcionario' => $this->contagemDecrescente($servicos->map(fn (Servico $s): string => $s->funcionario->nome)),
        ];
    }

    /**
     * Mais frequente primeiro; empate em ordem alfabética.
     *
     * @param  Collection<int, string>  $nomes
     * @return array<string, int>
     */
    private function contagemDecrescente(Collection $nomes): array
    {
        $contagem = $nomes->countBy()->all();

        uksort($contagem, fn (string $a, string $b): int => ($contagem[$b] <=> $contagem[$a]) ?: $this->collator->compare($a, $b));

        return $contagem;
    }

    /**
     * @param  Collection<int, TipoServico>  $tipos
     * @return Collection<int, string>
     */
    private function nomesOrdenados(Collection $tipos): Collection
    {
        return $tipos->pluck('nome')->sort(fn (string $a, string $b): int => $this->collator->compare($a, $b))->values();
    }

    private function periodoPorExtenso(Carbon $inicio, Carbon $fim): string
    {
        if ($this->ehMesInteiro($inicio, $fim)) {
            return $inicio->translatedFormat('F \d\e Y');
        }

        return $inicio->translatedFormat('j \d\e F \d\e Y').' a '.$fim->translatedFormat('j \d\e F \d\e Y');
    }

    /** RF-07.6 — `relatorio-servicos-2026-08.pdf`; período fora de mês fechado leva as duas datas. */
    private function nomeDoArquivo(Carbon $inicio, Carbon $fim): string
    {
        $sufixo = $this->ehMesInteiro($inicio, $fim)
            ? $inicio->format('Y-m')
            : $inicio->toDateString().'_'.$fim->toDateString();

        return "relatorio-servicos-{$sufixo}";
    }

    private function ehMesInteiro(Carbon $inicio, Carbon $fim): bool
    {
        return $inicio->isSameDay($inicio->copy()->startOfMonth())
            && $fim->isSameDay($inicio->copy()->endOfMonth());
    }

    /** Embarcação fora do alcance do solicitante não tem o nome revelado no cabeçalho. */
    private function rotuloEmbarcacao(int $embarcacaoId, User $solicitante): string
    {
        $embarcacao = Embarcacao::query()->forUser($solicitante)->find($embarcacaoId, ['nome', 'identificacao']);

        return $embarcacao ? "{$embarcacao->nome} ({$embarcacao->identificacao})" : "Embarcação nº {$embarcacaoId}";
    }
}
