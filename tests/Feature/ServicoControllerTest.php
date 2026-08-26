<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Invariantes de `services/servico_service.rs` (INV01–INV03) e o RBAC por papel. */
class ServicoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registra_servico_para_qualquer_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
                'descricao' => 'Limpeza, Motor',
                'data_execucao' => '2026-01-15',
            ])
            ->assertRedirect();

        $servico = Servico::query()->firstOrFail();
        $this->assertSame(Servico::STATUS_EM_EXECUCAO, $servico->status);
        $this->assertSame($admin->id, $servico->created_by_user_id);
        $this->assertSame($admin->id, $servico->updated_by_user_id);
    }

    /** INV01 — serviço sem embarcação não existe. */
    public function test_embarcacao_inexistente_e_rejeitada(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.store'), [
                'embarcacao_id' => 999,
                'funcionario_id' => $funcionario->id,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertSessionHasErrors('embarcacao_id');
    }

    /** INV02 — serviço sem funcionário não existe. */
    public function test_funcionario_inexistente_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => 999,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertSessionHasErrors('funcionario_id');
    }

    public function test_funcionario_inativo_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->inativo()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertSessionHasErrors('funcionario_id');

        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_descricao_e_data_sao_obrigatorias(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
            ])
            ->assertSessionHasErrors(['descricao', 'data_execucao']);
    }

    /** O `funcionario_id` enviado é ignorado e substituído pelo do usuário logado. */
    public function test_funcionario_e_atribuido_a_si_mesmo_ignorando_o_payload(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        $outro = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->doFuncionario($funcionario)->create();

        $this->actingAs($user)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $outro->id,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertRedirect();

        $this->assertSame($funcionario->id, Servico::query()->firstOrFail()->funcionario_id);
    }

    public function test_funcionario_nao_registra_servico_em_embarcacao_de_outro(): void
    {
        [$user] = $this->funcionarioLogado();
        $outro = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->doFuncionario($outro)->create();

        $this->actingAs($user)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $outro->id,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_usuario_sem_funcionario_vinculado_nao_registra_servico(): void
    {
        $user = User::factory()->create(['funcionario_id' => null]);
        $embarcacao = Embarcacao::factory()->create();
        $funcionario = Funcionario::factory()->create();

        $this->actingAs($user)
            ->post(route('servicos.store'), [
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
                'descricao' => 'Limpeza',
                'data_execucao' => '2026-01-15',
            ])
            ->assertForbidden();
    }

    public function test_admin_conclui_servico(): void
    {
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();

        $this->actingAs($admin)
            ->put(route('servicos.update', $servico), ['status' => Servico::STATUS_CONCLUIDO])
            ->assertRedirect();

        $servico->refresh();
        $this->assertSame(Servico::STATUS_CONCLUIDO, $servico->status);
        $this->assertSame($admin->id, $servico->updated_by_user_id);
    }

    /** INV03 — um serviço concluído não é reaberto, nem por admin. */
    public function test_servico_concluido_nao_e_reaberto_por_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->concluido()->create();

        $this->actingAs($admin)
            ->put(route('servicos.update', $servico), ['status' => Servico::STATUS_EM_EXECUCAO])
            ->assertSessionHasErrors('status');

        $this->assertSame(Servico::STATUS_CONCLUIDO, $servico->refresh()->status);
    }

    public function test_status_invalido_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();

        $this->actingAs($admin)
            ->put(route('servicos.update', $servico), ['status' => 'arquivado'])
            ->assertSessionHasErrors('status');
    }

    public function test_funcionario_edita_o_proprio_servico_em_execucao(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        $servico = Servico::factory()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->put(route('servicos.update', $servico), [
                'descricao' => 'Motor, Buzina',
                'observacao' => 'Troca de óleo',
            ])
            ->assertRedirect();

        $servico->refresh();
        $this->assertSame('Motor, Buzina', $servico->descricao);
        $this->assertSame('Troca de óleo', $servico->observacao);
    }

    public function test_funcionario_nao_edita_servico_de_outro(): void
    {
        [$user] = $this->funcionarioLogado();
        $servico = Servico::factory()->create();

        $this->actingAs($user)
            ->put(route('servicos.update', $servico), ['descricao' => 'Alterado'])
            ->assertForbidden();
    }

    public function test_funcionario_nao_edita_servico_concluido(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        $servico = Servico::factory()->concluido()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->put(route('servicos.update', $servico), ['descricao' => 'Alterado'])
            ->assertForbidden();
    }

    public function test_funcionario_nao_conclui_servico(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        $servico = Servico::factory()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->put(route('servicos.update', $servico), ['status' => Servico::STATUS_CONCLUIDO])
            ->assertForbidden();

        $this->assertSame(Servico::STATUS_EM_EXECUCAO, $servico->refresh()->status);
    }

    public function test_funcionario_nao_reatribui_servico(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        $outro = Funcionario::factory()->create();
        $servico = Servico::factory()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->put(route('servicos.update', $servico), ['funcionario_id' => $outro->id])
            ->assertForbidden();

        $this->assertSame($funcionario->id, $servico->refresh()->funcionario_id);
    }

    public function test_admin_ve_o_historico_completo(): void
    {
        $admin = User::factory()->admin()->create();
        Servico::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('servicos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Historico')
                ->has('servicos', 3));
    }

    public function test_funcionario_ve_apenas_os_proprios_servicos_no_historico(): void
    {
        [$user, $funcionario] = $this->funcionarioLogado();
        Servico::factory()->create(['funcionario_id' => $funcionario->id]);
        Servico::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('servicos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('servicos', 1));
    }

    public function test_historico_filtra_por_embarcacao(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();
        Servico::factory()->create(['embarcacao_id' => $embarcacao->id]);
        Servico::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('servicos.index', ['embarcacao_id' => $embarcacao->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('servicos', 1)
                ->where('servicos.0.embarcacao_id', $embarcacao->id));
    }

    /**
     * @return array{0: User, 1: Funcionario}
     */
    private function funcionarioLogado(): array
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);

        return [$user, $funcionario];
    }
}
