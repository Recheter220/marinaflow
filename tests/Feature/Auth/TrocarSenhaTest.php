<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** `auth_service::trocar_senha` — troca voluntária, já autenticado. */
class TrocarSenhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_troca_a_propria_senha(): void
    {
        $user = User::factory()->create(['password' => 'senhaAntiga1']);

        $this->actingAs($user)
            ->put('/trocar-senha', [
                'current_password' => 'senhaAntiga1',
                'password' => 'senhaNova123',
                'password_confirmation' => 'senhaNova123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('senhaNova123', $user->refresh()->password));
    }

    public function test_senha_atual_incorreta_e_rejeitada(): void
    {
        $user = User::factory()->create(['password' => 'senhaAntiga1']);

        $this->actingAs($user)
            ->put('/trocar-senha', [
                'current_password' => 'errada',
                'password' => 'senhaNova123',
                'password_confirmation' => 'senhaNova123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('senhaAntiga1', $user->refresh()->password));
    }

    public function test_confirmacao_divergente_e_rejeitada(): void
    {
        $user = User::factory()->create(['password' => 'senhaAntiga1']);

        $this->actingAs($user)
            ->put('/trocar-senha', [
                'current_password' => 'senhaAntiga1',
                'password' => 'senhaNova123',
                'password_confirmation' => 'outraCoisa123',
            ])
            ->assertSessionHasErrors('password');
    }
}
