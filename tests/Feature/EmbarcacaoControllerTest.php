<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbarcacaoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lista_todas_as_embarcacoes(): void
    {
        $admin = User::factory()->admin()->create();
        Embarcacao::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('embarcacoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Embarcacoes')
                ->has('embarcacoes', 3));
    }

    /** `embarcacao_service::listar` — funcionário só enxerga o que está vinculado a ele. */
    public function test_funcionario_lista_apenas_as_proprias_embarcacoes(): void
    {
        $funcionario = Funcionario::factory()->create();
        $outro = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);

        $minha = Embarcacao::factory()->doFuncionario($funcionario)->create();
        Embarcacao::factory()->doFuncionario($outro)->create();
        Embarcacao::factory()->create();

        $this->actingAs($user)
            ->get(route('embarcacoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('embarcacoes', 1)
                ->where('embarcacoes.0.id', $minha->id));
    }

    public function test_busca_filtra_por_nome_identificacao_ou_cliente(): void
    {
        $admin = User::factory()->admin()->create();
        Embarcacao::factory()->create(['nome' => 'Netuno', 'identificacao' => 'BR-0001-AAA']);
        Embarcacao::factory()->create(['nome' => 'Poseidon', 'identificacao' => 'BR-0002-BBB']);

        $this->actingAs($admin)
            ->get(route('embarcacoes.index', ['busca' => 'Netuno']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('embarcacoes', 1)
                ->where('embarcacoes.0.nome', 'Netuno'));
    }

    public function test_admin_cadastra_embarcacao(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('embarcacoes.store'), [
                'nome' => 'Netuno',
                'identificacao' => 'BR-1234-XYZ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('embarcacoes', [
            'nome' => 'Netuno',
            'identificacao' => 'BR-1234-XYZ',
            'status' => 'ativa',
        ]);
    }

    public function test_funcionario_nao_cadastra_embarcacao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('embarcacoes.store'), [
                'nome' => 'Netuno',
                'identificacao' => 'BR-1234-XYZ',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('embarcacoes', ['identificacao' => 'BR-1234-XYZ']);
    }

    public function test_funcionario_nao_edita_embarcacao(): void
    {
        $user = User::factory()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($user)
            ->put(route('embarcacoes.update', $embarcacao), [
                'nome' => 'Renomeada',
                'identificacao' => $embarcacao->identificacao,
                'status' => 'ativa',
            ])
            ->assertForbidden();
    }

    public function test_identificacao_duplicada_e_rejeitada(): void
    {
        $admin = User::factory()->admin()->create();
        Embarcacao::factory()->create(['identificacao' => 'BR-1234-XYZ']);

        $this->actingAs($admin)
            ->post(route('embarcacoes.store'), [
                'nome' => 'Outra',
                'identificacao' => 'BR-1234-XYZ',
            ])
            ->assertSessionHasErrors('identificacao');
    }

    public function test_nome_e_identificacao_sao_obrigatorios(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('embarcacoes.store'), [])
            ->assertSessionHasErrors(['nome', 'identificacao']);
    }

    public function test_status_invalido_e_rejeitado_na_edicao(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs($admin)
            ->put(route('embarcacoes.update', $embarcacao), [
                'nome' => $embarcacao->nome,
                'identificacao' => $embarcacao->identificacao,
                'status' => 'afundada',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_atribui_embarcacao_a_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $embarcacao = Embarcacao::factory()->create();
        $funcionario = Funcionario::factory()->create();

        $this->actingAs($admin)
            ->put(route('embarcacoes.update', $embarcacao), [
                'nome' => $embarcacao->nome,
                'identificacao' => $embarcacao->identificacao,
                'status' => 'em_manutencao',
                'funcionario_id' => $funcionario->id,
            ])
            ->assertRedirect();

        $embarcacao->refresh();
        $this->assertSame($funcionario->id, $embarcacao->funcionario_id);
        $this->assertSame('em_manutencao', $embarcacao->status);
    }
}
