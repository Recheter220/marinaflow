<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Porte dos testes de `services/auth_service.rs` (`test_login_admin_seed`,
 * `test_login_senha_errada`) para o fluxo por e-mail/sessão do Laravel.
 */
class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_semeado_consegue_entrar(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@marinaflow.local',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_senha_errada_e_rejeitada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@marinaflow.local',
            'password' => 'errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_desativado_nao_entra(): void
    {
        User::factory()->inativo()->create([
            'email' => 'joao@exemplo.com',
            'password' => 'segredo123',
        ]);

        $response = $this->post('/login', [
            'email' => 'joao@exemplo.com',
            'password' => 'segredo123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_convite_pendente_nao_entra(): void
    {
        User::factory()->convitePendente()->create(['email' => 'maria@exemplo.com']);

        $response = $this->post('/login', [
            'email' => 'maria@exemplo.com',
            'password' => 'qualquer',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** `guard::require_authenticated`: visitante não acessa rota protegida. */
    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/usuarios')->assertRedirect(route('login'));
    }

    /** Quem foi desativado durante a sessão é derrubado no próximo request. */
    public function test_sessao_de_usuario_desativado_e_encerrada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('embarcacoes.index'))->assertOk();

        $user->update(['ativo' => false]);

        $this->actingAs($user)->get(route('embarcacoes.index'))->assertRedirect(route('login'));
    }
}
