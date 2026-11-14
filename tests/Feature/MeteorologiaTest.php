<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MeteorologiaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeteorologiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2026-11-13T15:00:00-03:00'));
    }

    private function dados(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/meteorologia.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_funcionario_e_administrador_acessam_os_24_registros(): void
    {
        Http::fake([config('meteorologia.url') => Http::response($this->dados())]);

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/meteorologia')->assertOk()->assertInertia(fn ($page) => $page
                ->component('Meteorologia')->has('previsao.registros', 24)
                ->where('previsao.registros.0.data_hora', '2026-11-13T10:00')
                ->where('previsao.registros.0.volume_chuva_mm', 0)
                ->where('previsao.status', 'disponivel'));
        }

        Http::assertSentCount(1);
    }

    public function test_visitante_e_usuario_inativo_nao_consultam_a_fonte(): void
    {
        Http::fake();
        $this->get('/meteorologia')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->inativo()->create())->get('/meteorologia')->assertRedirect();
        Http::assertNothingSent();
    }

    public function test_cache_atualiza_apos_quinze_minutos(): void
    {
        Http::fake([config('meteorologia.url') => Http::response($this->dados())]);
        $service = app(MeteorologiaService::class);
        $primeira = $service->previsao();
        $this->travel(14)->minutes();
        $this->assertSame($primeira, $service->previsao());
        Http::assertSentCount(1);
        $this->travel(2)->minutes();
        $this->assertNotSame($primeira['consultado_em'], $service->previsao()['consultado_em']);
        Http::assertSentCount(2);
    }

    public function test_falha_preserva_ultima_consulta_e_limita_novas_tentativas(): void
    {
        Http::fakeSequence()->push($this->dados())->pushStatus(500);
        $service = app(MeteorologiaService::class);
        $primeira = $service->previsao();
        $this->travel(16)->minutes();
        $fallback = $service->previsao();
        $this->assertSame('cache', $fallback['status']);
        $this->assertSame($primeira['registros'], $fallback['registros']);
        $this->assertSame($primeira['consultado_em'], $fallback['consultado_em']);
        $this->assertSame($fallback, $service->previsao());
        Http::assertSentCount(2);
    }

    public function test_timeout_sem_cache_retorna_indisponibilidade(): void
    {
        Http::fake([config('meteorologia.url') => Http::failedConnection()]);
        $this->assertSame('indisponivel', app(MeteorologiaService::class)->previsao()['status']);
    }

    public function test_fonte_fora_do_ar_nao_impede_renderizacao_da_pagina(): void
    {
        Http::fake([config('meteorologia.url') => Http::response('', 404)]);
        $this->actingAs(User::factory()->create())->get('/meteorologia')
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('Meteorologia')->has('previsao.registros', 0)
            ->where('previsao.status', 'indisponivel')
            ->where('previsao.consultado_em', null));
    }

    public function test_consulta_concorrente_utiliza_cache_sem_nova_chamada(): void
    {
        Http::fake([config('meteorologia.url') => Http::response($this->dados())]);
        $service = app(MeteorologiaService::class);
        $primeira = $service->previsao();
        $this->travel(16)->minutes();
        $lock = Cache::lock('meteorologia:v1:lock', 10);
        $lock->get();
        try {
            $previsao = $service->previsao();
            $this->assertSame('cache', $previsao['status']);
            $this->assertSame($primeira['registros'], $previsao['registros']);
            Http::assertSentCount(1);
        } finally {
            $lock->release();
        }
    }

    public function test_respostas_invalidas_nao_substituem_cache_valido(): void
    {
        $ausente = $this->dados();
        unset($ausente[0]['vento_kmh']);
        $dataInvalida = $this->dados();
        $dataInvalida[0]['data_hora'] = '2026-02-30T10:00';
        $duplicada = $this->dados();
        $duplicada[1]['data_hora'] = $duplicada[0]['data_hora'];
        $intervalo = $this->dados();
        array_splice($intervalo, 1, 1);
        $percentual = $this->dados();
        $percentual[0]['chance_chuva_pct'] = 101;

        $invalidos = ['{invalido', [], ['erro' => true], $ausente, $dataInvalida, $duplicada, $intervalo, $percentual];
        $sequence = Http::fakeSequence()->push($this->dados());
        foreach ($invalidos as $invalido) {
            $sequence->push($invalido);
        }
        $service = app(MeteorologiaService::class);
        $primeira = $service->previsao();
        foreach ($invalidos as $invalido) {
            $this->travel(16)->minutes();
            $this->assertSame('cache', $service->previsao()['status']);
            $this->assertSame($primeira['registros'], $service->previsao()['registros']);
        }
    }

    public function test_valores_nulos_nao_sao_transformados_em_zero(): void
    {
        $dados = $this->dados();
        $dados[0]['onda_altura_m'] = null;
        Http::fake([config('meteorologia.url') => Http::response($dados)]);
        $this->assertNull(app(MeteorologiaService::class)->previsao()['registros'][0]['onda_altura_m']);
    }

    public function test_previsao_antiga_e_identificada_mesmo_em_consulta_recente(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-14T10:00:00-03:00'));
        Http::fake([config('meteorologia.url') => Http::response($this->dados())]);
        $previsao = app(MeteorologiaService::class)->previsao();
        $this->assertSame('expirada', $previsao['status']);
        $this->assertCount(24, $previsao['registros']);
        $this->assertArrayNotHasKey('gerado_em', $previsao);
    }
}
