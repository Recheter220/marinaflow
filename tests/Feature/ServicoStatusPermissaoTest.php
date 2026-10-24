<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ServicoStatusPermissaoTest extends TestCase
{
    use RefreshDatabase;

    /** Aborta antes de migrate:fresh se esta classe não estiver isolada em memória. */
    protected function beforeRefreshingDatabase(): void
    {
        if (! $this->app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.driver') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || ! empty(config('database.connections.sqlite.url'))) {
            throw new RuntimeException('Testes bloqueados: exigem testing e SQLite :memory: sem URL externa.');
        }

        $connection = $this->app->make('db')->connection();

        if ($connection->getConfig('database') !== ':memory:'
            || $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Testes bloqueados: conexão efetiva não é SQLite em memória.');
        }

        foreach ($connection->select('PRAGMA database_list') as $database) {
            if ($database->file !== '') {
                throw new RuntimeException('Testes bloqueados: SQLite possui banco persistente anexado.');
            }
        }
    }

    public static function mudancasDeStatus(): array
    {
        return [
            'execucao para vistoria' => [Servico::STATUS_EM_EXECUCAO, Servico::STATUS_AGUARDANDO_VISTORIA],
            'execucao para concluido' => [Servico::STATUS_EM_EXECUCAO, Servico::STATUS_CONCLUIDO],
            'vistoria para execucao' => [Servico::STATUS_AGUARDANDO_VISTORIA, Servico::STATUS_EM_EXECUCAO],
            'vistoria para concluido' => [Servico::STATUS_AGUARDANDO_VISTORIA, Servico::STATUS_CONCLUIDO],
        ];
    }

    #[DataProvider('mudancasDeStatus')]
    public function test_requisicao_manual_de_funcionario_nao_altera_status_nem_dados(string $atual, string $destino): void
    {
        [$user, $servico] = $this->servicoDoFuncionario($atual);
        $antes = $servico->refresh()->getRawOriginal();

        $this->actingAs($user)->putJson(route('servicos.update', $servico), [
            'status' => $destino,
            'descricao' => ['Buzina'],
            'observacao' => 'Tentativa de alteração',
        ])->assertForbidden();

        $this->assertSame($antes, $servico->refresh()->getRawOriginal());
    }

    public static function edicoesPermitidas(): array
    {
        return [
            'legado sem status' => [Servico::STATUS_EM_EXECUCAO, false],
            'legado com reenvio' => [Servico::STATUS_EM_EXECUCAO, true],
            'vistoria sem status' => [Servico::STATUS_AGUARDANDO_VISTORIA, false],
            'vistoria com reenvio' => [Servico::STATUS_AGUARDANDO_VISTORIA, true],
        ];
    }

    #[DataProvider('edicoesPermitidas')]
    public function test_funcionario_edita_dados_preservando_status(string $status, bool $reenviar): void
    {
        [$user, $servico] = $this->servicoDoFuncionario($status);
        $payload = [
            'descricao' => ['Buzina'],
            'observacao' => 'Dados editados',
            'data_execucao' => '2026-10-01',
        ];

        if ($reenviar) {
            $payload['status'] = $status;
        }

        $this->actingAs($user)->put(route('servicos.update', $servico), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $servico->refresh();
        $this->assertSame($status, $servico->status);
        $this->assertSame(['Buzina'], $servico->descricao);
        $this->assertSame('Dados editados', $servico->observacao);
        $this->assertSame('2026-10-01', $servico->data_execucao->toDateString());
        $this->assertSame($user->funcionario_id, $servico->funcionario_id);
        $this->assertSame($user->id, $servico->updated_by_user_id);
    }

    public function test_funcionario_nao_edita_dados_de_servico_concluido_mesmo_reenviando_status(): void
    {
        [$user, $servico] = $this->servicoDoFuncionario(Servico::STATUS_CONCLUIDO);
        $antes = $servico->refresh()->getRawOriginal();

        $this->actingAs($user)->putJson(route('servicos.update', $servico), [
            'status' => Servico::STATUS_CONCLUIDO,
            'descricao' => ['Buzina'],
        ])->assertForbidden();

        $this->assertSame($antes, $servico->refresh()->getRawOriginal());
    }

    public function test_funcionario_nao_edita_dados_de_outro_funcionario(): void
    {
        [$user] = $this->servicoDoFuncionario(Servico::STATUS_AGUARDANDO_VISTORIA);
        $servico = Servico::factory()->create();
        $antes = $servico->refresh()->getRawOriginal();

        $this->actingAs($user)->putJson(route('servicos.update', $servico), [
            'status' => $servico->status,
            'descricao' => ['Buzina'],
        ])->assertForbidden();

        $this->assertSame($antes, $servico->refresh()->getRawOriginal());
    }

    private function servicoDoFuncionario(string $status): array
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacao = Embarcacao::factory()->doFuncionario($funcionario)->create();
        $servico = Servico::factory()->create([
            'embarcacao_id' => $embarcacao->id,
            'funcionario_id' => $funcionario->id,
            'status' => $status,
        ]);

        return [$user, $servico];
    }
}
