<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmbarcacaoFichaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_acessa_ficha_e_recebe_props_diferidos(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();
        $servico = Servico::factory()->for($embarcacao)->create();
        ServicoFoto::factory()->for($servico)->create();

        $this->actingAs($admin)
            ->get(route('embarcacoes.show', $embarcacao), [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'EmbarcacaoFicha',
'X-Inertia-Partial-Data' => 'embarcacao,resumo,servicos,fotos',
            ])
            ->assertOk()
            ->assertJsonPath('component', 'EmbarcacaoFicha')
            ->assertJsonPath('props.embarcacao.id', $embarcacao->id)
            ->assertJsonPath('props.embarcacao.nome', $embarcacao->nome)
            ->assertJsonStructure([
                'props' => ['resumo', 'servicos', 'fotos']
            ]);
    }

    public function test_funcionario_acessa_propria_embarcacao(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacao = Embarcacao::factory()->doFuncionario($funcionario)->create();

        $this->actingAs($user)
            ->get(route('embarcacoes.show', $embarcacao))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('EmbarcacaoFicha')
                ->where('embarcacao.id', $embarcacao->id));
    }

    public function test_funcionario_recebe_404_para_embarcacao_nao_vinculada(): void
    {
        $funcionario = Funcionario::factory()->create();
        $outro = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacaoDoOutro = Embarcacao::factory()->doFuncionario($outro)->create();

        $this->actingAs($user)
            ->get(route('embarcacoes.show', $embarcacaoDoOutro))
            ->assertNotFound();
    }

    public function test_funcionario_recebe_404_para_embarcacao_sem_funcionario(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacaoSemVinculo = Embarcacao::factory()->create(['funcionario_id' => null]);

        $this->actingAs($user)
            ->get(route('embarcacoes.show', $embarcacaoSemVinculo))
            ->assertNotFound();
    }

    public function test_usuario_nao_autenticado_e_redirecionado_para_login(): void
    {
        $embarcacao = Embarcacao::factory()->create();

        $this->get(route('embarcacoes.show', $embarcacao))
            ->assertRedirect(route('login'));
    }

    public function test_resumo_conta_total_de_servicos_e_em_execucao(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();

        Servico::factory()->for($embarcacao)->count(3)->create();
        Servico::factory()->for($embarcacao)->create(['status' => Servico::STATUS_EM_EXECUCAO]);

        $this->actingAs($admin)
            ->get(route('embarcacoes.show', $embarcacao), [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'EmbarcacaoFicha',
                'X-Inertia-Partial-Data' => 'resumo',
            ])
            ->assertOk()
            ->assertJsonPath('props.resumo.total_servicos', 4)
            ->assertJsonPath('props.resumo.servicos_em_execucao', 1);
    }

    public function test_linha_do_tempo_e_paginada_em_20(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();
        Servico::factory()->for($embarcacao)->count(25)->create();

        $this->actingAs($admin)
            ->get(route('embarcacoes.show', $embarcacao), [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'EmbarcacaoFicha',
                'X-Inertia-Partial-Data' => 'servicos',
            ])
            ->assertOk()
            ->assertJsonPath('props.servicos.meta.per_page', 20)
            ->assertJsonPath('props.servicos.meta.total', 25)
            ->assertJsonPath('props.servicos.meta.last_page', 2)
            ->assertJsonCount(20, 'props.servicos.data');
    }

    public function test_linha_do_tempo_pagina_2_retorna_restantes(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();
        Servico::factory()->for($embarcacao)->count(25)->create();

        $this->actingAs($admin)
            ->get(route('embarcacoes.show', [$embarcacao, 'pagina' => 2]), [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'EmbarcacaoFicha',
                'X-Inertia-Partial-Data' => 'servicos',
            ])
            ->assertOk()
            ->assertJsonPath('props.servicos.meta.current_page', 2)
            ->assertJsonCount(5, 'props.servicos.data');
    }

    public function test_sem_n_mais_1_com_20_servicos_com_fotos(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();

        Servico::factory()
            ->for($embarcacao)
            ->count(20)
            ->create()
            ->each(fn (Servico $s) => ServicoFoto::factory()->for($s)->count(2)->create());

        DB::enableQueryLog();

        $this->actingAs($admin)
            ->get(route('embarcacoes.show', $embarcacao), [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'EmbarcacaoFicha',
                'X-Inertia-Partial-Data' => 'servicos,resumo,fotos',
            ])
            ->assertOk();

        $totalQueries = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            15,
            $totalQueries,
            "Esperado no máximo 15 queries, mas foram executadas {$totalQueries}. Possível N+1.",
        );
    }
}