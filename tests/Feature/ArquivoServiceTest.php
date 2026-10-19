<?php

namespace Tests\Feature;

use App\Services\ArquivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** MF-03 — disco `fotos` e o `ArquivoService` que o encapsula. */
class ArquivoServiceTest extends TestCase
{
    /** AC-03.1 */
    public function test_guardar_grava_o_arquivo_na_pasta_e_devolve_o_caminho(): void
    {
        Storage::fake('fotos');

        $path = app(ArquivoService::class)->guardar(UploadedFile::fake()->image('casco.jpg'), 'servicos/7');

        $this->assertStringStartsWith('servicos/7/', $path);
        Storage::disk('fotos')->assertExists($path);
    }

    /** RF-03.3 — validade padrão de 15 minutos. */
    public function test_url_temporaria_expira_em_quinze_minutos(): void
    {
        Storage::fake('fotos');
        $this->travelTo('2026-10-19 12:00:00');

        $url = app(ArquivoService::class)->urlTemporaria('servicos/7/casco.jpg');

        $this->assertSame(url('servicos/7/casco.jpg?expiration=1792412100'), $url);
    }

    public function test_remover_apaga_o_arquivo(): void
    {
        Storage::fake('fotos');
        Storage::disk('fotos')->put('servicos/7/casco.jpg', 'conteudo');

        app(ArquivoService::class)->remover('servicos/7/casco.jpg');

        Storage::disk('fotos')->assertMissing('servicos/7/casco.jpg');
    }

    /** AC-03.2 — sem credenciais o disco é local e serve o arquivo só por URL assinada. */
    public function test_sem_credenciais_disco_local_serve_por_url_assinada(): void
    {
        config(['filesystems.disks.fotos.root' => storage_path('framework/testing/disks/fotos-local')]);
        $service = app(ArquivoService::class);
        $path = $service->guardar(UploadedFile::fake()->create('casco.jpg', 10), 'servicos/7');

        $url = $service->urlTemporaria($path);

        $this->get($url)->assertOk();
        $this->get(url('arquivos/fotos/'.$path))->assertForbidden();

        $service->remover($path);
    }

    /** RF-03.3 — no R2 a URL é pré-assinada pela SigV4, sem chamada de rede. */
    public function test_com_driver_s3_url_temporaria_e_pre_assinada_no_bucket(): void
    {
        config(['filesystems.disks.fotos' => [
            'driver' => 's3',
            'key' => 'chave-de-teste',
            'secret' => 'segredo-de-teste',
            'region' => 'auto',
            'bucket' => 'marinaflow',
            'endpoint' => 'https://conta.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
        ]]);

        $url = app(ArquivoService::class)->urlTemporaria('servicos/7/casco.jpg');

        $this->assertStringStartsWith('https://conta.r2.cloudflarestorage.com/marinaflow/servicos/7/casco.jpg?', $url);
        $this->assertStringContainsString('X-Amz-Expires=900', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }
}
