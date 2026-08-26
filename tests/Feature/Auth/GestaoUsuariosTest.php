<?php

namespace Tests\Feature\Auth;

use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Invariantes de `auth_service`: ativar/desativar, editar e excluir. */
class GestaoUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_desativa_e_reativa_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $alvo = User::factory()->create();

        $this->actingAs($admin)->patch("/usuarios/{$alvo->id}/ativo", ['ativo' => false]);
        $this->assertFalse($alvo->refresh()->ativo);

        $this->actingAs($admin)->patch("/usuarios/{$alvo->id}/ativo", ['ativo' => true]);
        $this->assertTrue($alvo->refresh()->ativo);
    }

    public function test_admin_nao_desativa_a_si_mesmo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch("/usuarios/{$admin->id}/ativo", ['ativo' => false])
            ->assertSessionHasErrors('ativo');

        $this->assertTrue($admin->refresh()->ativo);
    }

    public function test_admin_nao_exclui_a_si_mesmo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete("/usuarios/{$admin->id}")
            ->assertSessionHasErrors('id');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_exclui_outro_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $alvo = User::factory()->create();

        $this->actingAs($admin)->delete("/usuarios/{$alvo->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $alvo->id]);
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'ocupado@exemplo.com']);

        $this->actingAs($admin)
            ->post('/usuarios', ['email' => 'ocupado@exemplo.com', 'role' => 'funcionario'])
            ->assertSessionHasErrors('email');
    }

    public function test_role_invalida_e_rejeitada(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/usuarios', ['email' => 'novo@exemplo.com', 'role' => 'superuser'])
            ->assertSessionHasErrors('role');
    }

    public function test_admin_edita_usuario_e_vincula_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $alvo = User::factory()->create();
        $funcionario = Funcionario::query()->create(['nome' => 'João Silva']);

        $this->actingAs($admin)
            ->put("/usuarios/{$alvo->id}", [
                'email' => 'novo-email@exemplo.com',
                'role' => 'admin',
                'funcionario_id' => $funcionario->id,
            ])
            ->assertRedirect();

        $alvo->refresh();
        $this->assertSame('novo-email@exemplo.com', $alvo->email);
        $this->assertSame('admin', $alvo->role);
        $this->assertSame($funcionario->id, $alvo->funcionario_id);
    }
}
