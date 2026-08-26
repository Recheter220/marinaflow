<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Camada de serviço — tradução de `services/auth_service.rs`.
 *
 * As regras que no Rust eram `guard::require_admin(session)?` no topo de cada
 * função vivem agora no middleware `admin`; o que sobra aqui são as invariantes
 * de negócio propriamente ditas (não desativar/excluir a si mesmo, papel válido,
 * disparo do convite).
 */
class UserService
{
    /**
     * `auth_service::criar_usuario`, sem a senha temporária: o usuário nasce sem
     * senha e recebe um link assinado para definir a dele.
     *
     * @param  array{email: string, role: string, funcionario_id: ?int, name: ?string}  $data
     */
    public function criar(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'password' => null,
            'role' => $data['role'],
            'funcionario_id' => $data['funcionario_id'] ?? null,
            'primeiro_acesso' => true,
            'ativo' => true,
        ]);

        $this->enviarLinkDefinicaoSenha($user);

        return $user;
    }

    /** `auth_service::editar_usuario`. */
    public function atualizar(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'],
            'role' => $data['role'],
            'funcionario_id' => $data['funcionario_id'] ?? null,
        ]);

        return $user;
    }

    /**
     * `auth_service::ativar_desativar_usuario`, incluindo a trava de
     * "não pode desativar o próprio usuário".
     */
    public function definirAtivo(User $user, User $atuante, bool $ativo): void
    {
        if (! $ativo && $user->is($atuante)) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não pode desativar seu próprio usuário.',
            ]);
        }

        $user->update(['ativo' => $ativo]);
    }

    /** `auth_service::excluir_usuario`. */
    public function excluir(User $user, User $atuante): void
    {
        if ($user->is($atuante)) {
            throw ValidationException::withMessages([
                'id' => 'Você não pode excluir seu próprio usuário.',
            ]);
        }

        $user->delete();
    }

    /**
     * Substitui `auth_service::resetar_senha`. Em vez de gerar uma senha
     * temporária visível para o admin, reenvia o mesmo link assinado do convite.
     */
    public function reenviarLink(User $user, User $atuante): void
    {
        if ($user->is($atuante)) {
            throw ValidationException::withMessages([
                'id' => "Use 'Trocar Senha' para alterar sua própria senha.",
            ]);
        }

        $this->enviarLinkDefinicaoSenha($user);
    }

    /**
     * Marca o convite como pendente e dispara o e-mail pelo broker padrão do
     * Laravel — o mesmo usado por "esqueci minha senha".
     *
     * O broker limita reenvios (`auth.passwords.users.throttle`, 60s por padrão)
     * e nesse caso não envia nada. Sem checar o status, a tela avisaria "link
     * enviado" para um e-mail que nunca saiu.
     */
    public function enviarLinkDefinicaoSenha(User $user): void
    {
        $user->forceFill(['primeiro_acesso' => true])->save();

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::ResetThrottled) {
            throw ValidationException::withMessages([
                'email' => 'Um link foi enviado há pouco. Aguarde alguns instantes antes de pedir outro.',
            ]);
        }

        if ($status !== Password::ResetLinkSent) {
            throw ValidationException::withMessages([
                'email' => 'Não foi possível enviar o link de acesso.',
            ]);
        }
    }
}
