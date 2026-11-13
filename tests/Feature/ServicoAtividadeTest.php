<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\TipoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MEL02 — atividade executada, exclusiva, gravada como o tipo do catálogo. */
class ServicoAtividadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_liga_a_atividade_ao_tipo_do_catalogo(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->registro('lavagem'))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Lavagem'], $this->nomesDosTipos(Servico::query()->sole()));
    }

    public function test_atividade_desconhecida_e_recusada(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->registro('pintura'))
            ->assertSessionHasErrors(['atividade' => 'Atividade executada inválida.']);

        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_atividade_fora_de_uso_nao_entra_em_servico_novo(): void
    {
        TipoServico::query()->where('nome', 'Lavagem')->update(['ativo' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->registro('lavagem'))
            ->assertSessionHasErrors(['atividade' => 'A atividade selecionada não está disponível no catálogo.']);

        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_edicao_troca_a_atividade_e_mantem_os_demais_tipos_antigos(): void
    {
        $servico = Servico::factory()->comTipos(['Limpeza', 'Motor'])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['atividade' => 'lavagem'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Lavagem', 'Motor'], $this->nomesDosTipos($servico));
    }

    public function test_edicao_mantem_atividade_fora_de_uso_ja_ligada(): void
    {
        $servico = Servico::factory()->comTipos(['Lavagem'])->create();
        TipoServico::query()->where('nome', 'Lavagem')->update(['ativo' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['atividade' => 'lavagem'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Lavagem'], $this->nomesDosTipos($servico));
    }

    public function test_historico_so_aponta_atividade_quando_ha_exatamente_uma(): void
    {
        $admin = User::factory()->admin()->create();
        $comUma = Servico::factory()->comTipos(['Lavagem', 'Motor'])->create(['data_execucao' => '2026-01-02']);
        Servico::factory()->comTipos(['Lavagem', 'Limpeza'])->create(['data_execucao' => '2026-01-01']);

        $this->actingAs($admin)
            ->get(route('servicos.index'))
            ->assertInertia(fn ($page) => $page
                ->where('servicos.0.id', $comUma->id)
                ->where('servicos.0.atividade', 'lavagem')
                ->where('servicos.1.atividade', null));
    }

    /**
     * @return array<string, mixed>
     */
    private function registro(string $atividade): array
    {
        return [
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'atividade' => $atividade,
            'data_execucao' => '2026-01-15',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function nomesDosTipos(Servico $servico): array
    {
        return $servico->tipos()->orderBy('nome')->pluck('nome')->all();
    }
}
