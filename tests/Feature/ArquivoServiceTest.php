<?php

namespace Tests\Feature;

use App\Services\ArquivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** MF-03 — discos de fotos e o `ArquivoService` que os encapsula. */
class ArquivoServiceTest extends TestCase
{
    /** AC-03.1 */
    public function test_guardar_grava_o_arquivo_na_pasta_e_devolve_o_caminho(): void
    {
        Storage::fake('fotos-local');

        $path = app(ArquivoService::class)->guardar(UploadedFile::fake()->image('casco.jpg'), 'servicos/7');

        $this->assertStringStartsWith('servicos/7/', $path);
        Storage::disk('fotos-local')->assertExists($path);
    }

    /** RF-03.3 — validade padrão de 15 minutos. */
    public function test_url_temporaria_expira_em_quinze_minutos(): void
    {
        Storage::fake('fotos-local');
        $this->travelTo('2026-10-19 12:00:00');

        $url = app(ArquivoService::class)->urlTemporaria('servicos/7/casco.jpg');

        $this->assertSame(url('servicos/7/casco.jpg?expiration=1792412100'), $url);
    }

    public function test_remover_apaga_o_arquivo(): void
    {
        Storage::fake('fotos-local');
        Storage::disk('fotos-local')->put('servicos/7/casco.jpg', 'conteudo');

        app(ArquivoService::class)->remover('servicos/7/casco.jpg');

        Storage::disk('fotos-local')->assertMissing('servicos/7/casco.jpg');
    }

    /** AC-03.2 — o padrão é `fotos-local`, que não pede credencial e serve o arquivo só por URL assinada. */
    public function test_disco_local_serve_o_arquivo_so_por_url_assinada(): void
    {
        config(['filesystems.disks.fotos-local.root' => storage_path('framework/testing/disks/fotos-local')]);
        $service = app(ArquivoService::class);
        $path = $service->guardar(UploadedFile::fake()->create('casco.jpg', 10), 'servicos/7');

        $url = $service->urlTemporaria($path);

        $this->get($url)->assertOk();
        $this->get(url('arquivos/fotos/'.$path))->assertForbidden();

        $service->remover($path);
    }

    /** RF-03.1 e RF-03.3 — o disco `fotos` gera URL pré-assinada no bucket, sem chamada de rede. */
    public function test_disco_fotos_gera_url_pre_assinada_no_bucket(): void
    {
        config([
            'filesystems.fotos_disk' => 'fotos',
            'filesystems.disks.fotos.key' => 'chave-de-teste',
            'filesystems.disks.fotos.secret' => 'segredo-de-teste',
            'filesystems.disks.fotos.region' => 'auto',
            'filesystems.disks.fotos.bucket' => 'marinaflow',
            'filesystems.disks.fotos.endpoint' => 'https://conta.r2.cloudflarestorage.com',
        ]);

        $url = app(ArquivoService::class)->urlTemporaria('servicos/7/casco.jpg');

        $this->assertStringStartsWith('https://conta.r2.cloudflarestorage.com/marinaflow/servicos/7/casco.jpg?', $url);
        $this->assertStringContainsString('X-Amz-Expires=900', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }
}
