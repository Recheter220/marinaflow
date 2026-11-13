<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** MF-07 — relatório de serviços (AC-07.1 a AC-07.4). */
class RelatorioServicoTest extends TestCase
{
    use RefreshDatabase;

    private const AGOSTO = ['inicio' => '2026-08-01', 'fim' => '2026-08-31'];

    protected function setUp(): void
    {
        parent::setUp();

        // `fim` não pode passar de amanhã; agosto inteiro precisa estar no passado.
        $this->travelTo('2026-09-10 12:00:00');
    }

    /** AC-07.1 */
    public function test_admin_baixa_o_pdf_do_mes_com_nome_do_arquivo_no_padrao(): void
    {
        $admin = User::factory()->admin()->create();
        Servico::factory()->count(5)->create(['data_execucao' => '2026-08-15']);
        Servico::factory()->count(2)->create(['data_execucao' => '2026-07-20']);

        $response = $this->actingAs($admin)->get(route('relatorios.servicos', self::AGOSTO));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('relatorio-servicos-2026-08.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'formato' => 'html']))
            ->assertOk()
            ->assertViewHas('resumo.total', 5)
            ->assertViewHas('linhas', fn (array $linhas): bool => count($linhas) === 5);
    }

    public function test_primeiro_e_ultimo_dia_do_periodo_entram_no_relatorio(): void
    {
        $admin = User::factory()->admin()->create();
        Servico::factory()->create(['data_execucao' => '2026-07-31']);
        Servico::factory()->create(['data_execucao' => '2026-08-01']);
        Servico::factory()->create(['data_execucao' => '2026-08-31']);
        Servico::factory()->create(['data_execucao' => '2026-09-01']);

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'formato' => 'html']))
            ->assertViewHas('linhas', fn (array $linhas): bool => array_column($linhas, 'data') === ['01/08/2026', '31/08/2026']);
    }

    public function test_periodo_fora_de_mes_fechado_leva_as_duas_datas_no_nome_do_arquivo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', ['inicio' => '2026-08-10', 'fim' => '2026-09-05']))
            ->assertDownload('relatorio-servicos-2026-08-10_2026-09-05.pdf');
    }

    /** AC-07.2 */
    public function test_funcionario_ve_apenas_os_proprios_servicos_mesmo_pedindo_os_de_outro(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $outro = Funcionario::factory()->create();
        Servico::factory()->count(2)->create(['funcionario_id' => $funcionario->id, 'data_execucao' => '2026-08-15']);
        Servico::factory()->count(3)->create(['funcionario_id' => $outro->id, 'data_execucao' => '2026-08-15']);

        $this->actingAs($user)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'funcionario_id' => $outro->id, 'formato' => 'html']))
            ->assertOk()
            ->assertViewHas('resumo.total', 2)
            ->assertViewHas('resumo.por_funcionario', [$funcionario->nome => 2])
            ->assertViewHas('filtros.funcionario', $funcionario->nome)
            ->assertDontSee($outro->nome);
    }

    public function test_cabecalho_nao_revela_embarcacao_fora_do_alcance_do_funcionario(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $alheia = Embarcacao::factory()->create();

        $this->actingAs($user)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'embarcacao_id' => $alheia->id, 'formato' => 'html']))
            ->assertOk()
            ->assertViewHas('filtros.embarcacao', "Embarcação nº {$alheia->id}")
            ->assertDontSee($alheia->nome);
    }

    public function test_admin_filtra_por_embarcacao_e_funcionario(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->create();
        Servico::factory()->create(['embarcacao_id' => $embarcacao->id, 'funcionario_id' => $funcionario->id, 'data_execucao' => '2026-08-15']);
        Servico::factory()->create(['embarcacao_id' => $embarcacao->id, 'data_execucao' => '2026-08-15']);
        Servico::factory()->create(['funcionario_id' => $funcionario->id, 'data_execucao' => '2026-08-15']);

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', [
                ...self::AGOSTO,
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
                'formato' => 'html',
            ]))
            ->assertOk()
            ->assertViewHas('resumo.total', 1)
            ->assertViewHas('filtros', [
                'embarcacao' => "{$embarcacao->nome} ({$embarcacao->identificacao})",
                'funcionario' => $funcionario->nome,
            ]);
    }

    public function test_resumo_conta_por_situacao_e_por_tipo_com_o_mais_frequente_primeiro(): void
    {
        $admin = User::factory()->admin()->create();
        Servico::factory()->comTipos(['Motor', 'Buzina'])->create(['data_execucao' => '2026-08-05']);
        Servico::factory()->comTipos(['Motor'])->concluido()->create(['data_execucao' => '2026-08-06']);

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'formato' => 'html']))
            ->assertViewHas('resumo.por_situacao', [
                'Em Execução' => 0,
                'Aguardando Vistoria' => 1,
                'Concluído' => 1,
            ])
            ->assertViewHas('resumo.por_tipo', fn (array $porTipo): bool => $porTipo === ['Motor' => 2, 'Buzina' => 1]);
    }

    /** AC-07.3 e RF-07.8 */
    #[DataProvider('periodosInvalidos')]
    public function test_periodo_invalido_e_recusado_com_mensagem_clara(string $inicio, string $fim, string $mensagem): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('relatorios.servicos', ['inicio' => $inicio, 'fim' => $fim]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim' => $mensagem]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function periodosInvalidos(): array
    {
        return [
            'fim antes do início' => ['2026-08-31', '2026-08-01', 'A data final deve ser igual ou posterior à inicial.'],
            'mais de 366 dias' => ['2025-01-01', '2026-01-02', 'O período pode ter no máximo 366 dias.'],
            'fim no futuro' => ['2026-09-01', '2026-09-30', 'A data final não pode estar no futuro.'],
        ];
    }

    public function test_ano_bissexto_inteiro_cabe_no_limite_de_366_dias(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', ['inicio' => '2024-01-01', 'fim' => '2024-12-31', 'formato' => 'html']))
            ->assertOk();
    }

    /** AC-07.4 */
    public function test_periodo_sem_servicos_gera_pdf_com_aviso_e_resumo_zerado(): void
    {
        $admin = User::factory()->admin()->create();
        Servico::factory()->create(['data_execucao' => '2026-07-20']);

        $pdf = $this->actingAs($admin)->get(route('relatorios.servicos', self::AGOSTO));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->actingAs($admin)
            ->get(route('relatorios.servicos', [...self::AGOSTO, 'formato' => 'html']))
            ->assertOk()
            ->assertSee('Nenhum serviço executado no período.')
            ->assertViewHas('resumo.total', 0)
            ->assertViewHas('resumo.por_tipo', []);
    }

    public function test_visitante_e_mandado_para_o_login(): void
    {
        $this->get(route('relatorios.servicos', self::AGOSTO))->assertRedirect(route('login'));
    }

    public function test_tela_de_filtros_oferece_funcionarios_so_ao_admin(): void
    {
        $this->withoutVite();

        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        Embarcacao::factory()->doFuncionario($funcionario)->create();
        Embarcacao::factory()->create();
        Funcionario::factory()->inativo()->create();

        $this->actingAs($user)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Relatorios')
                ->has('embarcacoes', 1)
                ->has('funcionarios', 0));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('relatorios.index'))
            ->assertInertia(fn ($page) => $page
                ->has('embarcacoes', 2)
                ->has('funcionarios', 2));
    }
}
