<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * D-04 — a migração precisa levar o texto de `servicos.descricao` que já está
 * em produção para a ligação com o catálogo, sem perder item, e voltar atrás.
 */
class TipoServicoBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_descricao_existente_vira_ligacao_com_o_catalogo_e_volta_no_rollback(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2])->assertSuccessful();

        $servicoId = DB::table('servicos')->insertGetId([
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'descricao' => json_encode(['Motor', ' motor ', 'Pintura']),
            'data_execucao' => '2026-08-10',
            'status' => 'concluido',
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('servicos', 'descricao'));
        $this->assertDatabaseHas('tipos_servico', ['nome' => 'Motor', 'ativo' => true]);
        $this->assertDatabaseHas('tipos_servico', ['nome' => 'Pintura', 'ativo' => false]);
        $this->assertEqualsCanonicalizing(['Motor', 'Pintura'], $this->nomesLigados($servicoId));

        $this->artisan('migrate:rollback', ['--step' => 2])->assertSuccessful();

        $this->assertSame(
            ['Motor', 'Pintura'],
            json_decode(DB::table('servicos')->where('id', $servicoId)->value('descricao'), true),
        );
        $this->assertDatabaseCount('tipos_servico', 0);
    }

    /**
     * @return array<int, string>
     */
    private function nomesLigados(int $servicoId): array
    {
        return DB::table('servico_tipo_servico')
            ->join('tipos_servico', 'tipos_servico.id', '=', 'servico_tipo_servico.tipo_servico_id')
            ->where('servico_id', $servicoId)
            ->pluck('tipos_servico.nome')
            ->all();
    }
}
