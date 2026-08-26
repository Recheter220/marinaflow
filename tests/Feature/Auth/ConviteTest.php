<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\DefinirSenhaNotification;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Substitui `test_criar_usuario_e_login` e `test_resetar_senha`: onde o app
 * Tauri devolvia uma senha temporária ao admin, agora sai um link assinado por
 * e-mail e o próprio usuário define a senha.
 */
class ConviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cria_usuario_e_convite_e_enviado(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/usuarios', [
                'email' => 'joao@exemplo.com',
                'role' => 'funcionario',
            ])
            ->assertRedirect();

        $novo = User::query()->where('email', 'joao@exemplo.com')->firstOrFail();

        // Nenhuma senha é gerada nem devolvida ao admin.
        $this->assertNull($novo->password);
        $this->assertTrue($novo->primeiro_acesso);

        Notification::assertSentTo($novo, DefinirSenhaNotification::class);
    }

    public function test_usuario_define_senha_pelo_link_e_consegue_entrar(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/usuarios', [
            'email' => 'joao@exemplo.com',
            'role' => 'funcionario',
        ]);

        $novo = User::query()->where('email', 'joao@exemplo.com')->firstOrFail();
        // O token vem do convite disparado pela criação; pedir outro agora
        // esbarraria no throttle do broker.
        $token = $this->capturarToken($novo);

        // Quem abre o link é o convidado, não o admin que ainda está logado na
        // sessão de teste — sem isso o middleware `guest` barra o acesso.
        Auth::logout();

        $this->post('/definir-senha', [
            'token' => $token,
            'email' => $novo->email,
            'password' => 'senhaSegura1',
            'password_confirmation' => 'senhaSegura1',
        ])->assertRedirect(route('login'));

        $novo->refresh();
        $this->assertNotNull($novo->password);
        $this->assertFalse($novo->primeiro_acesso);

        $this->post('/login', [
            'email' => 'joao@exemplo.com',
            'password' => 'senhaSegura1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($novo);
    }

    public function test_link_nao_pode_ser_reutilizado(): void
    {
        $user = User::factory()->convitePendente()->create(['email' => 'maria@exemplo.com']);
        $token = $this->tokenDoConvite($user);

        $dados = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'senhaSegura1',
            'password_confirmation' => 'senhaSegura1',
        ];

        $this->post('/definir-senha', $dados)->assertRedirect(route('login'));

        // O token é consumido pelo broker; a segunda tentativa falha.
        $this->post('/definir-senha', $dados)->assertSessionHasErrors('email');
    }

    public function test_reenviar_convite_dispara_novo_link(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $alvo = User::factory()->create(['email' => 'maria@exemplo.com']);

        $this->actingAs($admin)
            ->post("/usuarios/{$alvo->id}/convite")
            ->assertRedirect();

        Notification::assertSentTo($alvo, DefinirSenhaNotification::class);
        $this->assertTrue($alvo->refresh()->primeiro_acesso);
    }

    public function test_admin_nao_reenvia_link_para_si_mesmo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/usuarios/{$admin->id}/convite")
            ->assertSessionHasErrors('id');
    }

    /** Dispara o broker e devolve o token do link enviado. */
    private function tokenDoConvite(User $user): string
    {
        Notification::fake();

        app(UserService::class)->enviarLinkDefinicaoSenha($user);

        return $this->capturarToken($user);
    }

    /** Lê o token da notificação já capturada pelo `Notification::fake()`. */
    private function capturarToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo($user, DefinirSenhaNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return $token;
    }
}
