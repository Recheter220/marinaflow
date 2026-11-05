<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\TipoServico;
use App\Models\User;
use App\Services\RelatorioServicoService;
use App\Services\ServicoService;
use App\Services\VerificacaoItens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServicoVerificacaoItensTest extends TestCase
{
    use RefreshDatabase;

    private function verificacao(string $atividade = 'lavagem'): array
    {
        return [
            'equipamentos' => array_fill_keys(array_keys(VerificacaoItens::EQUIPAMENTOS), 'funcionamento_ok'),
            'atividade' => $atividade,
        ];
    }

    private function payload(): array
    {
        return [
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'data_execucao' => '2026-11-01',
            'verificacao_itens' => $this->verificacao(),
        ];
    }

    public static function atividades(): array
    {
        return [['lavagem'], ['limpeza']];
    }

    #[DataProvider('atividades')]
    public function test_registra_classificacoes_e_atividade_preservando_observacao_opcional(string $atividade): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();
        $payload['verificacao_itens'] = $this->verificacao($atividade);
        $payload['verificacao_itens']['equipamentos']['buzina'] = 'nao_se_aplica';
        $payload['verificacao_itens']['equipamentos']['motor'] = 'com_defeito';
        $payload['observacao'] = "Motor falha após ligar.\nCliente ciente.";

        $this->actingAs($admin)->post(route('servicos.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $servico = Servico::query()->firstOrFail();
        $this->assertSame($payload['verificacao_itens'], $servico->verificacao_itens);
        $this->assertSame($payload['observacao'], $servico->observacao);
        $this->assertSame(Servico::STATUS_AGUARDANDO_VISTORIA, $servico->status);
        $this->assertSame($admin->id, $servico->created_by_user_id);

        unset($payload['observacao']);
        $this->post(route('servicos.store'), $payload)->assertSessionHasNoErrors();
        $this->assertNull(Servico::query()->latest('id')->firstOrFail()->observacao);

        $this->get(route('servicos.index'))->assertInertia(fn ($page) => $page
            ->where('servicos.0.verificacao_itens', $payload['verificacao_itens']));
    }

    public static function invalidos(): array
    {
        $cases = [];
        foreach (array_keys(VerificacaoItens::EQUIPAMENTOS) as $codigo) {
            $cases[$codigo.' ausente'] = ['equipamentos.'.$codigo, '__ausente'];
            $cases[$codigo.' vazio'] = ['equipamentos.'.$codigo, ''];
        }

        return $cases + [
            'classificacao invalida' => ['equipamentos.motor', 'bom'],
            'duas classificacoes' => ['equipamentos.motor', ['com_defeito', 'funcionamento_ok']],
            'equipamento desconhecido' => ['equipamentos.outro', 'funcionamento_ok'],
            'sem atividade' => ['atividade', '__ausente'],
            'atividade vazia' => ['atividade', ''],
            'ambas atividades' => ['atividade', ['lavagem', 'limpeza']],
            'atividade invalida' => ['atividade', 'outra'],
            'observacao por item' => ['observacoes', ['motor' => 'falha']],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rejeita_criacao_e_edicao_invalidas_sem_mutacao(string $path, mixed $value): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();
        $servico = Servico::factory()->create(['verificacao_itens' => $this->verificacao()]);
        $before = $servico->fresh()->getAttributes();
        if ($value === '__ausente') {
            data_forget($payload['verificacao_itens'], $path);
        } else {
            data_set($payload['verificacao_itens'], $path, $value);
        }

        $this->actingAs($admin)->postJson(route('servicos.store'), $payload)->assertUnprocessable();
        $this->putJson(route('servicos.update', $servico), [
            'verificacao_itens' => $payload['verificacao_itens'],
            'observacao' => 'Não pode persistir',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('servicos', 1);
        $this->assertSame($before, $servico->fresh()->getAttributes());
    }

    public function test_nao_aceita_payload_antigo_para_novos_registros_nem_null_na_edicao(): void
    {
        $payload = $this->payload();
        unset($payload['verificacao_itens']);
        $payload['descricao'] = ['Lavagem'];
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('servicos.store'), $payload)->assertUnprocessable();
        $servico = Servico::factory()->create(['verificacao_itens' => $this->verificacao()]);
        $this->putJson(route('servicos.update', $servico), ['verificacao_itens' => null])
            ->assertUnprocessable();
        $this->assertSame($this->verificacao(), $servico->fresh()->verificacao_itens);
    }

    public function test_legado_preserva_descricao_e_nao_exige_classificacao_para_observacao_ou_status(): void
    {
        $servico = Servico::factory()->create([]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('servicos.update', $servico), ['observacao' => 'Detalhe geral'])
            ->assertSessionHasNoErrors();
        $this->put(route('servicos.update', $servico), ['status' => 'concluido'])->assertSessionHasNoErrors();
        $servico->refresh();
        $this->assertNull($servico->verificacao_itens);
        $this->assertEqualsCanonicalizing(['Limpeza', 'Motor'], $servico->tipos->pluck('nome')->all());
        $this->assertSame('Detalhe geral', $servico->observacao);
        $this->assertSame('concluido', $servico->status);
    }

    public function test_classifica_legado_explicitamente_preservando_itens_historicos_e_observacao(): void
    {
        $servico = Servico::factory()->comTipos(['Limpeza', 'Lavagem', 'Motor', 'Pintura'])->create(['observacao' => 'Original']);
        $snapshot = $servico->tipos()->orderBy('tipos_servico.id')->get(['tipos_servico.id', 'nome'])
            ->map(fn (TipoServico $tipo): array => ['id' => $tipo->id, 'nome' => $tipo->nome])->all();
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['verificacao_itens' => $this->verificacao()])
            ->assertSessionHasNoErrors();
        $servico->refresh();
        $this->assertEqualsCanonicalizing(['Motor', 'Pintura', 'Lavagem'], $servico->fresh()->tipos->pluck('nome')->all());
        $this->assertSame($snapshot, $servico->tipos_legados);
        $this->put(route('servicos.update', $servico), ['verificacao_itens' => $this->verificacao('limpeza')])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $servico->fresh()->tipos_legados);
        $this->get(route('servicos.index'))->assertInertia(fn ($page) => $page->where('servicos.0.tipos_legados', $snapshot));
        $this->assertSame('Original', $servico->observacao);
        $this->assertSame($this->verificacao('limpeza'), $servico->fresh()->verificacao_itens);
    }

    public function test_edita_classificacao_e_conclui_sem_apagar_dados_e_sem_reabrir(): void
    {
        $servico = Servico::factory()->create(['verificacao_itens' => $this->verificacao()]);
        $novo = $this->verificacao('limpeza');
        $novo['equipamentos']['motor'] = 'com_defeito';
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), ['verificacao_itens' => $novo])->assertSessionHasNoErrors();
        $this->put(route('servicos.update', $servico), ['status' => 'concluido'])->assertSessionHasNoErrors();
        $this->putJson(route('servicos.update', $servico), ['status' => 'aguardando_vistoria'])
            ->assertUnprocessable();
        $novo['equipamentos']['motor'] = 'funcionamento_ok';
        $this->put(route('servicos.update', $servico), ['verificacao_itens' => $novo])->assertSessionHasNoErrors();
        $this->assertSame($novo, $servico->fresh()->verificacao_itens);
        $this->assertSame('concluido', $servico->fresh()->status);
    }

    public function test_funcionario_edita_apenas_proprio_aberto_sem_alterar_status(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $servico = Servico::factory()->create([
            'funcionario_id' => $funcionario->id,
            'verificacao_itens' => $this->verificacao(),
        ]);
        $novo = $this->verificacao('limpeza');
        $this->actingAs($user)->put(route('servicos.update', $servico), ['verificacao_itens' => $novo])
            ->assertSessionHasNoErrors();
        $this->assertSame($novo, $servico->fresh()->verificacao_itens);
        $this->assertSame('aguardando_vistoria', $servico->fresh()->status);
        $this->putJson(route('servicos.update', $servico), ['status' => 'concluido', 'verificacao_itens' => $this->verificacao()])->assertForbidden();
        $outro = Servico::factory()->create();
        $this->putJson(route('servicos.update', $outro), ['verificacao_itens' => $novo])->assertForbidden();
        $servico->update(['status' => 'concluido']);
        $this->putJson(route('servicos.update', $servico), ['verificacao_itens' => $this->verificacao()])->assertForbidden();
        $this->assertSame($novo, $servico->fresh()->verificacao_itens);
    }

    public function test_service_tambem_exige_classificacao_na_criacao(): void
    {
        $payload = $this->payload();
        unset($payload['verificacao_itens']);
        $this->expectException(ValidationException::class);
        app(ServicoService::class)->criar($payload, User::factory()->admin()->create());
    }

    public function test_migration_up_down_preserva_registro_legado(): void
    {
        $migration = require database_path('migrations/2026_11_01_120000_add_verificacao_itens_to_servicos_table.php');
        $migration->down();
        $funcionario = Funcionario::factory()->create();
        $embarcacao = Embarcacao::factory()->create();
        $id = DB::table('servicos')->insertGetId([
            'funcionario_id' => $funcionario->id,
            'embarcacao_id' => $embarcacao->id,
            'data_execucao' => '2026-10-01',
            'status' => 'em_execucao',
            'observacao' => 'Registro original',
        ]);
        $before = (array) DB::table('servicos')->find($id);
        $migration->up();
        $after = (array) DB::table('servicos')->find($id);
        $this->assertNull($after['verificacao_itens']);
        unset($after['verificacao_itens']);
        $this->assertSame($before, $after);
        $migration->down();
        $this->assertSame($before, (array) DB::table('servicos')->find($id));
        $migration->up();
    }

    public function test_catalogo_inativo_nao_cria_servico_parcial(): void
    {
        TipoServico::query()->where('nome', 'Lavagem')->update(['ativo' => false]);
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('servicos.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('verificacao_itens.atividade');
        $this->assertDatabaseCount('servicos', 0);
        $this->assertDatabaseCount('servico_tipo_servico', 0);
    }

    public function test_atividade_e_derivada_no_servidor_e_nao_pode_ser_contornada_por_tipos(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('servicos.store'), $this->payload())->assertSessionHasNoErrors();
        $servico = Servico::query()->sole();
        $this->assertSame(['Lavagem'], $servico->tipos->pluck('nome')->all());
        $this->putJson(route('servicos.update', $servico), ['tipos' => $this->idsDosTipos('Lavagem', 'Limpeza')])
            ->assertUnprocessable();
        $this->assertSame(['Lavagem'], $servico->fresh()->tipos->pluck('nome')->all());
        $this->put(route('servicos.update', $servico), ['verificacao_itens' => $this->verificacao('limpeza')])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Limpeza'], $servico->fresh()->tipos->pluck('nome')->all());
        $relatorio = app(RelatorioServicoService::class)->montar([
            'inicio' => '2026-11-01', 'fim' => '2026-11-01',
        ], auth()->user());
        $this->assertSame(['Limpeza' => 1], $relatorio['resumo']['por_tipo']);
        $this->assertSame('Limpeza', $relatorio['linhas'][0]['tipos']);
    }

    public function test_arquiva_descricao_exatamente_antes_de_normalizar_historico(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 6])->assertSuccessful();
        $original = '["Motor", " motor ", "Pintura", "Motor"]';
        $id = DB::table('servicos')->insertGetId([
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'descricao' => $original,
            'observacao' => "Texto original\nSegunda linha",
            'data_execucao' => '2026-08-10', 'status' => 'concluido',
        ]);
        $this->artisan('migrate')->assertSuccessful();
        $servico = Servico::query()->findOrFail($id);
        $this->assertSame($original, $servico->descricao_legada);
        $this->assertSame("Texto original\nSegunda linha", $servico->observacao);
        $this->assertSame('concluido', $servico->status);
        $this->assertNull($servico->verificacao_itens);
        $this->assertEqualsCanonicalizing(['Motor', 'Pintura'], $servico->tipos->pluck('nome')->all());
    }

    public function test_arquivo_legado_nao_inventa_texto_quando_develop_ja_removeu_a_coluna(): void
    {
        $migration = require database_path('migrations/2026_11_01_000047_preserve_servicos_descricao_legada.php');
        $migration->down();
        $servico = Servico::factory()->create();
        $tipos = $servico->tipos->pluck('id')->all();
        $migration->up();
        $this->assertNull($servico->fresh()->descricao_legada);
        $this->assertSame($tipos, $servico->fresh()->tipos->pluck('id')->all());
    }

    public function test_vincula_atividade_de_mel02_anterior_sem_inventar_classificacao_ou_perder_dados(): void
    {
        $servico = Servico::factory()->create(['verificacao_itens' => $this->verificacao(), 'observacao' => 'Original']);
        $servico->tipos()->detach();
        $antes = $servico->fresh()->getAttributes();
        $legado = Servico::factory()->comTipos(['Motor'])->create();
        $migration = require database_path('migrations/2026_11_02_000000_link_existing_mel02_activities.php');
        $migration->up();
        $migration->up();
        $this->assertSame($antes, $servico->fresh()->getAttributes());
        $this->assertSame(['Lavagem'], $servico->fresh()->tipos->pluck('nome')->all());
        $this->assertNull($legado->fresh()->verificacao_itens);
        $this->assertSame(['Motor'], $legado->fresh()->tipos->pluck('nome')->all());
    }

    public function test_mantem_atividade_inativa_ja_vinculada_ao_editar_observacao(): void
    {
        $servico = Servico::factory()->comTipos(['Lavagem'])->create(['verificacao_itens' => $this->verificacao()]);
        TipoServico::query()->where('nome', 'Lavagem')->update(['ativo' => false]);
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('servicos.update', $servico), [
                'verificacao_itens' => $this->verificacao(), 'observacao' => 'Correção geral',
            ])->assertSessionHasNoErrors();
        $this->assertSame('Correção geral', $servico->fresh()->observacao);
        $this->assertSame(['Lavagem'], $servico->fresh()->tipos->pluck('nome')->all());
        $this->assertSame('aguardando_vistoria', $servico->fresh()->status);
    }

    public function test_nao_atribui_atividade_inativa_nova_e_reverte_toda_edicao(): void
    {
        $servico = Servico::factory()->comTipos(['Lavagem'])->create(['verificacao_itens' => $this->verificacao()]);
        TipoServico::query()->where('nome', 'Limpeza')->update(['ativo' => false]);
        $before = $servico->fresh()->getAttributes();
        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('servicos.update', $servico), [
                'verificacao_itens' => $this->verificacao('limpeza'), 'observacao' => 'Não persistir',
            ])->assertUnprocessable();
        $this->assertSame($before, $servico->fresh()->getAttributes());
        $this->assertSame(['Lavagem'], $servico->fresh()->tipos->pluck('nome')->all());
    }

    public function test_rollback_protege_classificacoes_e_nao_altera_historico_de_migrations(): void
    {
        $servico = Servico::factory()->comTipos(['Lavagem'])->create(['verificacao_itens' => $this->verificacao()]);
        $antes = $servico->fresh()->getAttributes();
        $migrations = DB::table('migrations')->get()->toArray();
        try {
            $this->artisan('migrate:rollback');
            $this->fail('Rollback deveria proteger classificações.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }
        $this->assertEquals($migrations, DB::table('migrations')->get()->toArray());
        $this->assertSame($antes, $servico->fresh()->getAttributes());
    }

    public function test_rollback_direto_de_coluna_com_dados_e_bloqueado(): void
    {
        $servico = Servico::factory()->create(['verificacao_itens' => $this->verificacao()]);
        $migration = require database_path('migrations/2026_11_01_120000_add_verificacao_itens_to_servicos_table.php');
        try {
            $migration->down();
            $this->fail('A classificação não pode ser descartada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }
        $this->assertSame($this->verificacao(), $servico->fresh()->verificacao_itens);
    }
}
