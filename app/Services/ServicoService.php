<?php

namespace App\Services;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\TipoServico;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Camada de serviço — tradução de `services/servico_service.rs`, onde vivem as
 * invariantes do domínio (INV01–INV03) e o RBAC por papel.
 */
class ServicoService
{
    /**
     * @param  array{embarcacao_id: int, funcionario_id: int, tipos: array<int, int>, data_execucao: string, observacao: ?string}  $data
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

        return DB::transaction(function () use ($data, $autor): Servico {
            $servico = Servico::query()->create([
                ...Arr::except($data, 'tipos'),
                'status' => Servico::STATUS_AGUARDANDO_VISTORIA,
                'created_by_user_id' => $autor->id,
                'updated_by_user_id' => $autor->id,
            ]);

            $servico->tipos()->sync($data['tipos']);

            return $servico;
        });
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

        // O legado pode ser reenviado sem mudança, mas não é destino de transição.
        if ($novoStatus === Servico::STATUS_EM_EXECUCAO
            && $servico->status !== Servico::STATUS_EM_EXECUCAO) {
            throw ValidationException::withMessages([
                'status' => 'Serviço não pode retornar ao status em execução.',
            ]);
        }

        if (array_key_exists('tipos', $data)) {
            $this->recusarTipoInativoNovo($servico, $data['tipos']);
        }

        return DB::transaction(function () use ($servico, $data, $autor): Servico {
            $servico->update([...Arr::except($data, 'tipos'), 'updated_by_user_id' => $autor->id]);

            if (array_key_exists('tipos', $data)) {
                $servico->tipos()->sync($data['tipos']);
            }

            return $servico;
        });
    }

    /**
     * Regra de autoria: administrador mexe em qualquer serviço; funcionário só
     * nos próprios, ainda não concluídos. Também vale para as fotos (INV05).
     */
    public function autorizarAutoria(Servico $servico, User $autor): void
    {
        if ($autor->isAdmin()) {
            return;
        }

        if ($servico->funcionario_id !== $this->funcionarioDoUsuario($autor)) {
            throw new AccessDeniedHttpException(
                'Acesso negado. Você só pode editar seus próprios serviços.',
            );
        }

        if ($servico->status === Servico::STATUS_CONCLUIDO) {
            throw new AccessDeniedHttpException(
                'Serviços concluídos não podem ser editados por funcionários.',
            );
        }
    }

    /**
     * Funcionário edita apenas os próprios serviços, ainda não concluídos, sem
     * poder alterar o status nem reatribuí-los.
     *
     * @param  array<string, mixed>  $data
     */
    private function autorizarEdicaoPorFuncionario(
        Servico $servico,
        array $data,
        ?string $novoStatus,
        User $autor,
    ): void {
        $this->autorizarAutoria($servico, $autor);

        $funcionarioId = $this->funcionarioDoUsuario($autor);

        if ($novoStatus === Servico::STATUS_CONCLUIDO) {
            throw new AccessDeniedHttpException(
                'Somente administradores podem concluir serviços.',
            );
        }

        if ($novoStatus !== null && $novoStatus !== $servico->status) {
            throw new AccessDeniedHttpException(
                'Somente administradores podem alterar o status dos serviços.',
            );
        }

        if (array_key_exists('funcionario_id', $data)
            && $data['funcionario_id'] !== null
            && (int) $data['funcionario_id'] !== $funcionarioId) {
            throw new AccessDeniedHttpException('Você não pode reatribuir este serviço.');
        }
    }

    /**
     * Tipo desativado continua valendo nos serviços que já o têm, mas não entra
     * em serviço nenhum a partir de agora.
     *
     * @param  array<int, int>  $tipos
     */
    private function recusarTipoInativoNovo(Servico $servico, array $tipos): void
    {
        $novos = array_diff($tipos, $servico->tipos()->pluck('tipos_servico.id')->all());

        if (TipoServico::query()->whereKey($novos)->where('ativo', false)->exists()) {
            throw ValidationException::withMessages([
                'tipos' => 'Tipo de serviço fora de uso não pode ser adicionado.',
            ]);
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
