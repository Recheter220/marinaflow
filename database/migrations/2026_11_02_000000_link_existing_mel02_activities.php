<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Compatibilidade com bancos que já receberam a MEL02 anterior a develop. */
return new class extends Migration
{
    public function up(): void
    {
        $nomes = ['lavagem' => 'Lavagem', 'limpeza' => 'Limpeza'];
        $tipos = DB::table('tipos_servico')->whereIn('nome', $nomes)->pluck('id', 'nome');

        DB::table('servicos')->whereNotNull('verificacao_itens')->orderBy('id')
            ->chunkById(500, function ($servicos) use ($nomes, $tipos): void {
                foreach ($servicos as $servico) {
                    $atividade = json_decode($servico->verificacao_itens, true)['atividade'] ?? null;
                    $tipoId = isset($nomes[$atividade ?? '']) ? $tipos->get($nomes[$atividade]) : null;

                    if ($tipoId === null) {
                        throw new RuntimeException("Atividade MEL02 sem correspondência no catálogo: serviço {$servico->id}.");
                    }

                    $existentes = DB::table('servico_tipo_servico')->where('servico_id', $servico->id)
                        ->whereIn('tipo_servico_id', $tipos->values()->all())->pluck('tipo_servico_id')->all();

                    if (array_diff($existentes, [$tipoId]) !== []) {
                        throw new RuntimeException("Atividades conflitantes no serviço {$servico->id}; revisar antes de migrar.");
                    }

                    DB::table('servico_tipo_servico')->insertOrIgnore([
                        'servico_id' => $servico->id, 'tipo_servico_id' => $tipoId,
                    ]);
                }
            });
    }

    /** Os vínculos são dados de domínio e permanecem no rollback. */
    public function down(): void
    {
        if (DB::table('servicos')->whereNotNull('verificacao_itens')->exists()
            || DB::table('servicos')->whereNotNull('descricao_legada')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem classificações MEL02 ou dados históricos preservados.');
        }
    }
};
