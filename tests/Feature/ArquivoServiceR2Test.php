<?php

namespace Tests\Feature;

use App\Services\ArquivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * MF-03 contra o bucket real do Cloudflare R2. Fora da suíte padrão: rode com
 * `php artisan test --group=r2`, com as credenciais `AWS_*` no `.env`.
 *
 * Tudo é gravado sob `tests/` no bucket, para que restos de uma execução
 * interrompida sejam fáceis de achar e apagar.
 */
#[Group('r2')]
class ArquivoServiceR2Test extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        if (blank(config('filesystems.disks.fotos.key')) || blank(config('filesystems.disks.fotos.secret'))) {
            $this->markTestSkipped('Credenciais do R2 ausentes no .env.');
        }

        config(['filesystems.fotos_disk' => 'fotos']);
        $this->pasta = 'tests/'.Str::uuid();
    }

    protected function tearDown(): void
    {
        if (isset($this->pasta)) {
            Storage::disk('fotos')->deleteDirectory($this->pasta);
        }

        parent::tearDown();
    }

    /** AC-03.1 e RF-03.3 — o arquivo gravado no bucket é lido de volta pela URL assinada. */
    public function test_arquivo_gravado_no_bucket_e_lido_pela_url_assinada(): void
    {
        $service = app(ArquivoService::class);

        $path = $service->guardar(UploadedFile::fake()->createWithContent('casco.jpg', 'foto do casco'), $this->pasta);

        $this->assertStringStartsWith($this->pasta.'/', $path);
        $resposta = Http::get($service->urlTemporaria($path));
        $this->assertTrue($resposta->ok());
        $this->assertSame('foto do casco', $resposta->body());
    }

    /** RF-03.1 e RF-03.3 — o bucket é privado: sem assinatura o objeto não é servido. */
    public function test_bucket_recusa_leitura_sem_assinatura(): void
    {
        $path = app(ArquivoService::class)->guardar(UploadedFile::fake()->createWithContent('casco.jpg', 'foto do casco'), $this->pasta);

        $url = Str::before(app(ArquivoService::class)->urlTemporaria($path), '?');

        $this->assertTrue(Http::get($url)->clientError());
    }

    public function test_remover_apaga_o_arquivo_do_bucket(): void
    {
        $service = app(ArquivoService::class);
        $path = $service->guardar(UploadedFile::fake()->createWithContent('casco.jpg', 'foto do casco'), $this->pasta);

        $service->remover($path);

        $this->assertFalse(Storage::disk('fotos')->exists($path));
    }
}
