<?php

namespace App\Repositories;

use App\Models\Embarcacao;
use Illuminate\Support\Facades\DB;

class EmbarcacaoRepository
{
    /**
     * @return array<int, array{nome: string, total: int}>
     */
    public function tiposFrequentes(Embarcacao $embarcacao, int $limite = 5): array
    {
        return DB::table('servico_tipo_servico as sts')
            ->join('tipos_servico as ts', 'ts.id', '=', 'sts.tipo_servico_id')
            ->join('servicos as s', 's.id', '=', 'sts.servico_id')
            ->where('s.embarcacao_id', $embarcacao->id)
            ->groupBy('ts.id', 'ts.nome')
            ->orderByDesc('total')
            ->orderBy('ts.nome')
            ->limit($limite)
            ->get(['ts.nome', DB::raw('COUNT(*) as total')])
            ->map(fn (object $row): array => [
                'nome'  => $row->nome,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
