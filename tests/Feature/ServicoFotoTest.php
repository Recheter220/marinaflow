<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** MF-04 — fotos do serviço executado: autoria (RF-04.5/INV05), limites e disco. */
class ServicoFotoTest extends TestCase
{
    use RefreshDatabase;

    /** AC-04.1 */
    public function test_funcionario_anexa_fotos_ao_proprio_servico_e_elas_aparecem_no_historico(): void
    {
        Storage::fake('fotos-local');
        [$user, $funcionario] = $this->funcionarioLogado();
        $servico = Servico::factory()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->post(route('servicos.fotos.store', $servico), [
                'fotos' => [
                    UploadedFile::fake()->image('proa.jpg'),
                    UploadedFile::fake()->image('popa.jpg'),
                    UploadedFile::fake()->image('casco.jpg'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '3 fotos anexadas.');

        $fotos = $servico->fotos()->get();
        $this->assertSame(['proa.jpg', 'popa.jpg', 'casco.jpg'], $fotos->pluck('nome_original')->all());
        $this->assertSame([1, 2, 3], $fotos->pluck('ordem')->all());
        $this->assertSame('image/jpeg', $fotos[0]->mime_type);
        $this->assertSame($user->id, $fotos[0]->uploaded_by_user_id);
        $fotos->each(fn (ServicoFoto $foto) => Storage::disk('fotos-local')->assertExists($foto->path));

        $this->actingAs($user)
            ->get(route('servicos.index'))
            ->assertInertia(fn ($page) => $page
                ->has('servicos.0.fotos', 3)
                ->where('servicos.0.fotos.0.id', $fotos[0]->id)
                ->where('servicos.0.fotos.0.nome_original', 'proa.jpg')
                ->where('servicos.0.fotos.0.url', fn (string $url) => str_contains($url, $fotos[0]->path.'?expiration=')));
    }

    /** AC-04.2 */
    public function test_funcionario_nao_anexa_foto_em_servico_de_outro(): void
    {
        Storage::fake('fotos-local');
        [$user] = $this->funcionarioLogado();
        $servico = Servico::factory()->create();

        $this->actingAs($user)
            ->post(route('servicos.fotos.store', $servico), ['fotos' => [UploadedFile::fake()->image('proa.jpg')]])
            ->assertForbidden();

        $this->assertSame(0, ServicoFoto::query()->count());
        $this->assertSame([], Storage::disk('fotos-local')->allFiles());
    }

    /** INV05 — concluído não recebe foto por ação de funcionário. */
    public function test_funcionario_nao_anexa_foto_em_servico_concluido(): void
    {
        Storage::fake('fotos-local');
        [$user, $funcionario] = $this->funcionarioLogado();
        $servico = Servico::factory()->concluido()->create(['funcionario_id' => $funcionario->id]);

        $this->actingAs($user)
            ->post(route('servicos.fotos.store', $servico), ['fotos' => [UploadedFile::fake()->image('proa.jpg')]])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('fotos-local')->allFiles());
    }

    /** INV05 — administrador anexa em qualquer serviço, inclusive concluído. */
    public function test_admin_anexa_foto_em_servico_concluido(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->concluido()->create();

        $this->actingAs($admin)
            ->post(route('servicos.fotos.store', $servico), ['fotos' => [UploadedFile::fake()->image('proa.jpg')]])
            ->assertSessionHas('success', 'Foto anexada.');

        $this->assertSame(1, $servico->fotos()->count());
    }

    /** AC-04.3 */
    public function test_servico_com_vinte_fotos_rejeita_mais_uma(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();
        ServicoFoto::factory()->count(20)->create(['servico_id' => $servico->id]);

        $this->actingAs($admin)
            ->post(route('servicos.fotos.store', $servico), ['fotos' => [UploadedFile::fake()->image('proa.jpg')]])
            ->assertSessionHasErrors(['fotos' => 'Este serviço já atingiu o limite de 20 fotos.']);

        $this->assertSame(20, $servico->fotos()->count());
    }

    /** O limite soma as fotos existentes às do envio atual. */
    public function test_envio_que_ultrapassa_o_limite_e_rejeitado_inteiro(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();
        ServicoFoto::factory()->count(19)->create(['servico_id' => $servico->id]);

        $this->actingAs($admin)
            ->post(route('servicos.fotos.store', $servico), [
                'fotos' => [UploadedFile::fake()->image('proa.jpg'), UploadedFile::fake()->image('popa.jpg')],
            ])
            ->assertSessionHasErrors(['fotos' => 'Este serviço aceita só mais 1 foto(s).']);

        $this->assertSame(19, $servico->fotos()->count());
    }

    /** AC-04.4 */
    public function test_pdf_junto_com_jpegs_retorna_422_apontando_o_indice(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('servicos.fotos.store', $servico), [
                'fotos' => [
                    UploadedFile::fake()->image('proa.jpg'),
                    UploadedFile::fake()->create('orcamento.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->image('popa.jpg'),
                ],
            ])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['fotos.1' => 'Formato não aceito: envie JPEG, PNG ou WebP.']);

        $this->assertSame(0, $servico->fotos()->count());
    }

    /** RF-04.2 */
    public function test_foto_acima_de_dez_mb_e_rejeitada(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.fotos.store', $servico), [
                'fotos' => [UploadedFile::fake()->image('proa.jpg')->size(10241)],
            ])
            ->assertSessionHasErrors(['fotos.0' => 'A foto excede o limite de 10 MB.']);
    }

    /** RF-04.1 */
    public function test_envio_de_mais_de_dez_fotos_de_uma_vez_e_rejeitado(): void
    {
        Storage::fake('fotos-local');
        $admin = User::factory()->admin()->create();
        $servico = Servico::factory()->create();

        $this->actingAs($admin)
            ->post(route('servicos.fotos.store', $servico), [
                'fotos' => array_map(fn (int $i) => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 11)),
            ])
            ->assertSessionHasErrors(['fotos' => 'Envie no máximo 10 fotos por vez.']);
    }

    /** RF-04.6 */
    public function test_funcionario_remove_foto_do_proprio_servico_e_o_arquivo_some(): void
    {
        Storage::fake('fotos-local');
        [$user, $funcionario] = $this->funcionarioLogado();
        $foto = $this->fotoGravada(Servico::factory()->create(['funcionario_id' => $funcionario->id]));

        $this->actingAs($user)
            ->delete(route('servicos.fotos.destroy', $foto))
            ->assertSessionHas('success', 'Foto removida.');

        $this->assertModelMissing($foto);
        Storage::disk('fotos-local')->assertMissing($foto->path);
    }

    /** RF-04.5 */
    public function test_funcionario_nao_remove_foto_de_servico_de_outro(): void
    {
        Storage::fake('fotos-local');
        [$user] = $this->funcionarioLogado();
        $foto = $this->fotoGravada(Servico::factory()->create());

        $this->actingAs($user)
            ->delete(route('servicos.fotos.destroy', $foto))
            ->assertForbidden();

        $this->assertModelExists($foto);
        Storage::disk('fotos-local')->assertExists($foto->path);
    }

    /** INV05 — concluído não perde foto por ação de funcionário. */
    public function test_funcionario_nao_remove_foto_de_servico_concluido(): void
    {
        Storage::fake('fotos-local');
        [$user, $funcionario] = $this->funcionarioLogado();
        $foto = $this->fotoGravada(Servico::factory()->concluido()->create(['funcionario_id' => $funcionario->id]));

        $this->actingAs($user)
            ->delete(route('servicos.fotos.destroy', $foto))
            ->assertForbidden();

        $this->assertModelExists($foto);
    }

    /** INV04 */
    public function test_excluir_servico_remove_as_fotos_em_cascata(): void
    {
        $foto = ServicoFoto::factory()->create();

        $foto->servico->delete();

        $this->assertModelMissing($foto);
    }

    public function test_link_da_foto_redireciona_para_url_assinada(): void
    {
        Storage::fake('fotos-local');
        [$user, $funcionario] = $this->funcionarioLogado();
        $foto = $this->fotoGravada(Servico::factory()->create(['funcionario_id' => $funcionario->id]));
        $this->travelTo('2026-10-26 12:00:00');

        $this->actingAs($user)
            ->get(route('servicos.fotos.show', $foto))
            ->assertRedirect(url($foto->path.'?expiration=1793016900'));
    }

    public function test_funcionario_nao_abre_foto_de_servico_de_outro(): void
    {
        Storage::fake('fotos-local');
        [$user] = $this->funcionarioLogado();
        $foto = $this->fotoGravada(Servico::factory()->create());

        $this->actingAs($user)
            ->get(route('servicos.fotos.show', $foto))
            ->assertForbidden();
    }

    /** A tela de registro precisa do id do serviço novo para enviar as fotos em seguida. */
    public function test_registro_de_servico_devolve_o_id_criado(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('servicos.store'), [
            'embarcacao_id' => Embarcacao::factory()->create()->id,
            'funcionario_id' => Funcionario::factory()->create()->id,
            'descricao' => ['Limpeza'],
            'data_execucao' => '2026-01-15',
        ]);

        $response->assertInertiaFlash('servico_id', Servico::query()->sole()->id);
    }

    private function fotoGravada(Servico $servico): ServicoFoto
    {
        $path = "servicos/{$servico->id}/proa.jpg";
        Storage::disk('fotos-local')->put($path, 'conteudo');

        return ServicoFoto::factory()->create(['servico_id' => $servico->id, 'path' => $path]);
    }

    /**
     * @return array{0: User, 1: Funcionario}
     */
    private function funcionarioLogado(): array
    {
        $funcionario = Funcionario::factory()->create();
        $user = User::factory()->create(['funcionario_id' => $funcionario->id]);

        return [$user, $funcionario];
    }
}
