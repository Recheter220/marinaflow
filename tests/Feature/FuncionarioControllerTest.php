<?php

namespace Tests\Feature;

use App\Models\Funcionario;
use App\Models\User;
use App\Notifications\DefinirSenhaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FuncionarioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_lista_funcionarios(): void
    {
        $user = User::factory()->create();
        Funcionario::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('funcionarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funcionarios')
                ->has('funcionarios', 2));
    }

    public function test_busca_filtra_por_nome_ou_cargo(): void
    {
        $user = User::factory()->create();
        Funcionario::factory()->create(['nome' => 'João Silva', 'cargo' => 'Mecânico']);
        Funcionario::factory()->create(['nome' => 'Maria Souza', 'cargo' => 'Pintor']);

        $this->actingAs($user)
            ->get(route('funcionarios.index', ['busca' => 'Pintor']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('funcionarios', 1)
                ->where('funcionarios.0.nome', 'Maria Souza'));
    }

    public function test_admin_cadastra_funcionario_sem_acesso_ao_sistema(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('funcionarios.store'), [
                'nome' => 'João Silva',
                'cargo' => 'Mecânico',
                'telefone' => '11999998888',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('funcionarios', ['nome' => 'João Silva', 'ativo' => true]);
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    /**
     * Substitui o usuário auto-criado com senha temporária de
     * `funcionario_service::criar`: o acesso é opcional e sai como convite.
     */
    public function test_email_informado_cria_usuario_vinculado_e_envia_convite(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('funcionarios.store'), [
                'nome' => 'João Silva',
                'email' => 'joao@exemplo.com',
            ])
            ->assertRedirect();

        $funcionario = Funcionario::query()->where('nome', 'João Silva')->firstOrFail();
        $novo = User::query()->where('email', 'joao@exemplo.com')->firstOrFail();

        $this->assertSame($funcionario->id, $novo->funcionario_id);
        $this->assertSame(User::ROLE_FUNCIONARIO, $novo->role);
        $this->assertNull($novo->password);
        Notification::assertSentTo($novo, DefinirSenhaNotification::class);
    }

    public function test_email_ja_em_uso_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'ocupado@exemplo.com']);

        $this->actingAs($admin)
            ->post(route('funcionarios.store'), [
                'nome' => 'João Silva',
                'email' => 'ocupado@exemplo.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('funcionarios', ['nome' => 'João Silva']);
    }

    public function test_nome_e_obrigatorio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('funcionarios.store'), ['cargo' => 'Mecânico'])
            ->assertSessionHasErrors('nome');
    }

    public function test_funcionario_nao_cadastra_funcionario(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('funcionarios.store'), ['nome' => 'João Silva'])
            ->assertForbidden();

        $this->assertDatabaseMissing('funcionarios', ['nome' => 'João Silva']);
    }

    public function test_funcionario_nao_edita_funcionario(): void
    {
        $user = User::factory()->create();
        $funcionario = Funcionario::factory()->create();

        $this->actingAs($user)
            ->put(route('funcionarios.update', $funcionario), [
                'nome' => 'Alterado',
                'ativo' => true,
            ])
            ->assertForbidden();
    }

    public function test_admin_desativa_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->create();

        $this->actingAs($admin)
            ->put(route('funcionarios.update', $funcionario), [
                'nome' => $funcionario->nome,
                'cargo' => 'Eletricista',
                'ativo' => false,
            ])
            ->assertRedirect();

        $funcionario->refresh();
        $this->assertFalse($funcionario->ativo);
        $this->assertSame('Eletricista', $funcionario->cargo);
    }
}
