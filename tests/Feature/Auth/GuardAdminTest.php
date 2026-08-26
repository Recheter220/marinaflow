<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Porte de `auth/guard.rs::require_admin` e de
 * `auth_service::test_funcionario_nao_pode_criar_usuario`.
 */
class GuardAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_acessa_gestao_de_usuarios(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/usuarios')->assertOk();
    }

    public function test_funcionario_nao_acessa_gestao_de_usuarios(): void
    {
        $funcionario = User::factory()->create();

        $this->actingAs($funcionario)->get('/usuarios')->assertForbidden();
    }

    public function test_funcionario_nao_pode_criar_usuario(): void
    {
        $funcionario = User::factory()->create();

        $this->actingAs($funcionario)
            ->post('/usuarios', [
                'email' => 'outro@exemplo.com',
                'role' => 'funcionario',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'outro@exemplo.com']);
    }

    public function test_funcionario_nao_pode_excluir_usuario(): void
    {
        $funcionario = User::factory()->create();
        $alvo = User::factory()->create();

        $this->actingAs($funcionario)
            ->delete("/usuarios/{$alvo->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $alvo->id]);
    }
}
