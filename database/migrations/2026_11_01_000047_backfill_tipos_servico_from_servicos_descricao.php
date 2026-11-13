<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D-04 — preenche o catálogo e a ligação a partir do texto de `servicos.descricao`.
 * Nada se perde: texto fora do catálogo vira tipo inativo, que some do formulário
 * mas continua no histórico e no relatório.
 */
return new class extends Migration
{
    /** O catálogo que vivia em `resources/js/utils/servicos.js`. */
    private const CATALOGO = [
        'Buzina',
        'Guincho da âncora',
        'Lavagem',
        'Limpeza',
        'Luzes de navegação',
        'Motor',
        'Rádio de som',
        'radio VHF',
    ];

    public function up(): void
    {
        $agora = now();

        DB::table('tipos_servico')->insert(array_map(
            fn (string $nome): array => ['nome' => $nome, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora],
            self::CATALOGO,
        ));

        /** @var array<string, int> $idPorChave */
        $idPorChave = DB::table('tipos_servico')->pluck('id', 'nome')
            ->mapWithKeys(fn (int $id, string $nome): array => [$this->chave($nome) => $id])
            ->all();

        DB::table('servicos')->select(['id', 'descricao'])->orderBy('id')
            ->chunk(500, function (Collection $servicos) use (&$idPorChave, $agora): void {
                $ligacoes = [];

                foreach ($servicos as $servico) {
                    foreach ((array) json_decode($servico->descricao ?? '[]', true) as $item) {
                        $nome = trim((string) $item);

                        if ($nome === '') {
                            continue;
                        }

                        $idPorChave[$this->chave($nome)] ??= DB::table('tipos_servico')->insertGetId([
                            'nome' => $nome,
                            'ativo' => false,
                            'created_at' => $agora,
                            'updated_at' => $agora,
                        ]);

                        $tipoId = $idPorChave[$this->chave($nome)];
                        $ligacoes["{$servico->id}-{$tipoId}"] = ['servico_id' => $servico->id, 'tipo_servico_id' => $tipoId];
                    }
                }

                DB::table('servico_tipo_servico')->insert(array_values($ligacoes));
            });
    }

    /** Devolve os nomes para `descricao` (recriada pelo `down()` da migration seguinte). */
    public function down(): void
    {
        DB::table('servicos')->select('id')->orderBy('id')->chunk(500, function (Collection $servicos): void {
            $nomesPorServico = DB::table('servico_tipo_servico')
                ->join('tipos_servico', 'tipos_servico.id', '=', 'servico_tipo_servico.tipo_servico_id')
                ->whereIn('servico_id', $servicos->pluck('id'))
                ->orderBy('tipos_servico.nome')
                ->get(['servico_id', 'tipos_servico.nome'])
                ->groupBy('servico_id');

            foreach ($servicos as $servico) {
                DB::table('servicos')->where('id', $servico->id)->update([
                    'descricao' => json_encode(
                        $nomesPorServico->get($servico->id)?->pluck('nome')->all() ?? [],
                        JSON_UNESCAPED_UNICODE,
                    ),
                ]);
            }
        });

        DB::table('servico_tipo_servico')->delete();
        DB::table('tipos_servico')->delete();
    }

    /** "Motor", " motor " e "MOTOR" são o mesmo tipo. */
    private function chave(string $nome): string
    {
        return mb_strtolower(trim($nome));
    }
};
