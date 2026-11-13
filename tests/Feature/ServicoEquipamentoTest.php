<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Equipamento;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MEL02 — condição de cada equipamento; sem vínculo, "não se aplica". */
class ServicoEquipamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_grava_apenas_as_condicoes_diferentes_de_nao_se_aplica(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->registro([
                $this->equipamento('Motor') => Equipamento::CONDICAO_COM_DEFEITO,
                $this->equipamento('Buzina') => Equipamento::CONDICAO_FUNCIONAMENTO_OK,
                $this->equipamento('Rádio VHF') => Equipamento::CONDICAO_NAO_SE_APLICA,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Buzina' => Equipamento::CONDICAO_FUNCIONAMENTO_OK, 'Motor' => Equipamento::CONDICAO_COM_DEFEITO],
            $this->condicoes(Servico::query()->firstOrFail()),
        );
    }

    public function test_edicao_substitui_as_condicoes_e_remove_o_que_passa_a_nao_se_aplicar(): void
    {
        $servico = $this->servicoComMotorComDefeito();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['equipamentos' => [
                $this->equipamento('Motor') => Equipamento::CONDICAO_NAO_SE_APLICA,
                $this->equipamento('Buzina') => Equipamento::CONDICAO_FUNCIONAMENTO_OK,
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Buzina' => Equipamento::CONDICAO_FUNCIONAMENTO_OK], $this->condicoes($servico));
    }

    public function test_edicao_sem_a_verificacao_mantem_as_condicoes(): void
    {
        $servico = $this->servicoComMotorComDefeito();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['observacao' => 'Revisado'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Motor' => Equipamento::CONDICAO_COM_DEFEITO], $this->condicoes($servico));
    }

    public function test_condicao_desconhecida_e_recusada(): void
    {
        $motor = $this->equipamento('Motor');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->registro([$motor => 'quebrado']))
            ->assertSessionHasErrors(["equipamentos.{$motor}" => 'Condição do equipamento inválida.']);

        $this->assertDatabaseCount('servicos', 0);
    }

    public function test_equipamento_inexistente_e_recusado(): void
    {
        $servico = $this->servicoComMotorComDefeito();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['equipamentos' => [999 => Equipamento::CONDICAO_COM_DEFEITO]])
            ->assertSessionHasErrors(['equipamentos' => 'Equipamento inexistente na verificação.']);

        $this->assertSame(['Motor' => Equipamento::CONDICAO_COM_DEFEITO], $this->condicoes($servico));
    }

    public function test_telas_recebem_os_equipamentos_e_o_historico_traz_os_vinculos(): void
    {
        $admin = User::factory()->admin()->create();
        $servico = $this->servicoComMotorComDefeito();

        $this->actingAs($admin)
            ->get(route('servicos.create'))
            ->assertInertia(fn ($page) => $page->has('equipamentos', 6)->where('equipamentos.0.nome', 'Buzina'));

        $this->actingAs($admin)
            ->get(route('servicos.index'))
            ->assertInertia(fn ($page) => $page
                ->has('equipamentos', 6)
                ->where('servicos.0.id', $servico->id)
                ->where('servicos.0.equipamentos', [
                    ['id' => $this->equipamento('Motor'), 'condicao' => Equipamento::CONDICAO_COM_DEFEITO],
                ]));
    }

    /**
     * @param  array<int, string>  $equipamentos
     * @return array<string, mixed>
     */
    private function registro(array $equipamentos): array
    {
        return [
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'atividade' => 'limpeza',
            'equipamentos' => $equipamentos,
            'data_execucao' => '2026-01-15',
        ];
    }

    private function servicoComMotorComDefeito(): Servico
    {
        $servico = Servico::factory()->create();
        $servico->equipamentos()->attach($this->equipamento('Motor'), ['condicao' => Equipamento::CONDICAO_COM_DEFEITO]);

        return $servico;
    }

    /** Equipamento semeado pela migration da MEL02. */
    private function equipamento(string $nome): int
    {
        return Equipamento::query()->where('nome', $nome)->value('id');
    }

    /**
     * @return array<string, string> nome => condição
     */
    private function condicoes(Servico $servico): array
    {
        return $servico->equipamentos()->orderBy('nome')->get()
            ->mapWithKeys(fn (Equipamento $e): array => [$e->nome => $e->pivot->condicao])
            ->all();
    }
}
