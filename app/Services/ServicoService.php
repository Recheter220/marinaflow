<?php

namespace App\Services;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Camada de serviço — tradução de `services/servico_service.rs`, onde vivem as
 * invariantes do domínio (INV01–INV03) e o RBAC por papel.
 */
class ServicoService
{
    /**
     * @param  array{embarcacao_id: int, funcionario_id: int, descricao: array<int, string>, data_execucao: string, observacao: ?string}  $data
     */
    public function criar(array $data, User $autor): Servico
    {
        if (! $autor->isAdmin()) {
            $data['funcionario_id'] = $this->funcionarioDoUsuario($autor);

            $embarcacao = Embarcacao::query()->findOrFail($data['embarcacao_id']);

            if ($embarcacao->funcionario_id !== $data['funcionario_id']) {
                throw new AccessDeniedHttpException(
                    'Você não tem permissão para registrar serviços nesta embarcação.',
                );
            }
        }

        $funcionario = Funcionario::query()->findOrFail($data['funcionario_id']);

        if (! $funcionario->ativo) {
            throw ValidationException::withMessages([
                'funcionario_id' => 'Funcionário selecionado está inativo.',
            ]);
        }

        return Servico::query()->create([
            ...$data,
            'status' => Servico::STATUS_EM_EXECUCAO,
            'created_by_user_id' => $autor->id,
            'updated_by_user_id' => $autor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function atualizar(Servico $servico, array $data, User $autor): Servico
    {
        $novoStatus = $data['status'] ?? null;

        if (! $autor->isAdmin()) {
            $this->autorizarEdicaoPorFuncionario($servico, $data, $novoStatus, $autor);
        }

        // INV03 — um serviço concluído não é reaberto, nem por admin.
        if ($servico->status === Servico::STATUS_CONCLUIDO
            && $novoStatus !== null
            && $novoStatus !== Servico::STATUS_CONCLUIDO) {
            throw ValidationException::withMessages([
                'status' => 'Serviço concluído não pode ter status alterado.',
            ]);
        }

        $servico->update([...$data, 'updated_by_user_id' => $autor->id]);

        return $servico;
    }

    /**
     * Funcionário edita apenas os próprios serviços, ainda não concluídos, sem
     * poder concluí-los nem reatribuí-los.
     *
     * @param  array<string, mixed>  $data
     */
    private function autorizarEdicaoPorFuncionario(
        Servico $servico,
        array $data,
        ?string $novoStatus,
        User $autor,
    ): void {
        $funcionarioId = $this->funcionarioDoUsuario($autor);

        if ($servico->funcionario_id !== $funcionarioId) {
            throw new AccessDeniedHttpException(
                'Acesso negado. Você só pode editar seus próprios serviços.',
            );
        }

        if ($servico->status === Servico::STATUS_CONCLUIDO) {
            throw new AccessDeniedHttpException(
                'Serviços concluídos não podem ser editados por funcionários.',
            );
        }

        if ($novoStatus === Servico::STATUS_CONCLUIDO) {
            throw new AccessDeniedHttpException(
                'Somente administradores podem concluir serviços.',
            );
        }

        if (array_key_exists('funcionario_id', $data)
            && $data['funcionario_id'] !== null
            && (int) $data['funcionario_id'] !== $funcionarioId) {
            throw new AccessDeniedHttpException('Você não pode reatribuir este serviço.');
        }
    }

    private function funcionarioDoUsuario(User $user): int
    {
        if ($user->funcionario_id === null) {
            throw new AccessDeniedHttpException(
                'Usuário funcionário sem ID de funcionário vinculado.',
            );
        }

        return $user->funcionario_id;
    }
}
