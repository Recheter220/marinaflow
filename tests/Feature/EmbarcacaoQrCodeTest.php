<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;
use Tests\TestCase;

class EmbarcacaoQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_embarcacao_recebe_ulid_na_criacao(): void
    {
        $embarcacao = Embarcacao::factory()->create();

        $this->assertTrue(Str::isUlid($embarcacao->ulid));
        $this->assertIsInt($embarcacao->id);
    }

    public function test_migration_preenche_o_ulid_das_embarcacoes_existentes(): void
    {
        $this->artisan('migrate:rollback', ['--step' => $this->passosAteOUlid()])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('embarcacoes', 'ulid'));

        DB::table('embarcacoes')->insert([
            ['nome' => 'Maré Alta', 'identificacao' => 'BR-0001', 'status' => 'ativa'],
            ['nome' => 'Brisa', 'identificacao' => 'BR-0002', 'status' => 'ativa'],
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $ulids = DB::table('embarcacoes')->pluck('ulid');
        $this->assertCount(2, $ulids->filter(fn (?string $ulid) => Str::isUlid((string) $ulid)));
        $this->assertCount(2, $ulids->unique());
    }

    /** AC-09.1 */
    public function test_admin_escaneia_e_vai_para_a_ficha(): void
    {
        $embarcacao = Embarcacao::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('embarcacoes.qr', $embarcacao->ulid))
            ->assertRedirect(route('embarcacoes.show', $embarcacao));
    }

    /** AC-09.2 */
    public function test_visitante_faz_login_e_chega_a_ficha_pretendida(): void
    {
        $embarcacao = Embarcacao::factory()->create();
        $admin = User::factory()->admin()->create(['password' => 'senha-segura']);
        $qr = route('embarcacoes.qr', $embarcacao->ulid);

        $this->get($qr)->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'senha-segura'])
            ->assertRedirect($qr);

        $this->get($qr)->assertRedirect(route('embarcacoes.show', $embarcacao));
    }

    /** AC-09.3 */
    public function test_funcionario_nao_vinculado_recebe_negativa_sem_dados_da_embarcacao(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacao = Embarcacao::factory()->doFuncionario(Funcionario::factory()->create())->create();

        $resposta = $this->actingAs($user)->get(route('embarcacoes.qr', $embarcacao->ulid));

        $resposta->assertNotFound();
        $resposta->assertDontSee($embarcacao->nome);
        $resposta->assertDontSee(route('embarcacoes.show', $embarcacao));
    }

    public function test_funcionario_vinculado_escaneia_e_vai_para_a_ficha(): void
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);
        $embarcacao = Embarcacao::factory()->doFuncionario($funcionario)->create();

        $this->actingAs($user)
            ->get(route('embarcacoes.qr', $embarcacao->ulid))
            ->assertRedirect(route('embarcacoes.show', $embarcacao));
    }

    public function test_ulid_inexistente_retorna_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('embarcacoes.qr', (string) Str::ulid()))
            ->assertNotFound();
    }

    public function test_svg_do_qr_e_gerado_no_servidor_e_baixa_como_anexo(): void
    {
        $embarcacao = Embarcacao::factory()->create(['identificacao' => 'BR-1234']);
        $admin = User::factory()->admin()->create();

        $svg = $this->actingAs($admin)->get(route('embarcacoes.qrcode', $embarcacao));
        $svg->assertOk()->assertHeader('content-type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svg->getContent());

        $this->actingAs($admin)
            ->get(route('embarcacoes.qrcode', ['embarcacao' => $embarcacao, 'download' => 1]))
            ->assertDownload('qrcode-br-1234.svg');
    }

    public function test_svg_respeita_a_regra_de_visibilidade(): void
    {
        $user = User::factory()->create(['funcionario_id' => Funcionario::factory()->create()->id]);
        $embarcacao = Embarcacao::factory()->doFuncionario(Funcionario::factory()->create())->create();

        $this->actingAs($user)
            ->get(route('embarcacoes.qrcode', $embarcacao))
            ->assertNotFound();
    }

    /** AC-09.4 */
    public function test_folha_de_etiquetas_tem_10_por_pagina_das_ativas(): void
    {
        Embarcacao::factory()->count(12)->create();
        $inativa = Embarcacao::factory()->create(['status' => 'inativa']);

        $html = $this->etiquetasEmHtml([]);
        $this->assertSame(12, substr_count($html, 'alt="QR Code de '));
        $this->assertSame(2, substr_count($html, 'class="pagina"'));
        $this->assertStringNotContainsString($inativa->identificacao, $html);

        $pdf = $this->actingAs(User::factory()->admin()->create())->get(route('embarcacoes.etiquetas'));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf->getContent()));
    }

    public function test_folha_de_etiquetas_aceita_uma_selecao(): void
    {
        [$escolhida, $fora] = Embarcacao::factory()->count(2)->create();

        $html = $this->etiquetasEmHtml([$escolhida->id]);

        $this->assertStringContainsString($escolhida->identificacao, $html);
        $this->assertStringNotContainsString($fora->identificacao, $html);
    }

    public function test_funcionario_nao_gera_folha_de_etiquetas(): void
    {
        $user = User::factory()->create(['funcionario_id' => Funcionario::factory()->create()->id]);

        $this->actingAs($user)->get(route('embarcacoes.etiquetas'))->assertForbidden();
    }

    /**
     * HTML da folha como o DomPDF o recebe, capturado no composer da view —
     * o texto dentro do PDF vem comprimido e não dá para conferir.
     *
     * @param  array<int, int>  $selecao
     */
    private function etiquetasEmHtml(array $selecao): string
    {
        $dados = null;

        View::composer('embarcacoes.etiquetas', function (ViewInstance $view) use (&$dados): void {
            $dados ??= $view->getData();
        });

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('embarcacoes.etiquetas', ['embarcacoes' => $selecao]))
            ->assertOk();

        return view('embarcacoes.etiquetas', $dados)->render();
    }

    /** Volta até antes do ULID, por mais migrations que venham depois dele. */
    private function passosAteOUlid(): int
    {
        return DB::table('migrations')
            ->where('migration', '>=', '2026_11_13_015844_add_ulid_to_embarcacoes_table')
            ->count();
    }
}
