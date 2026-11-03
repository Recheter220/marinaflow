<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CepIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /** Resposta do ViaCEP para 01310-100, como o serviço real devolve. */
    private function respostaViaCep(): array
    {
        return [
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'complemento' => 'de 612 a 1510 - lado par',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ];
    }

    public function test_cep_existente_devolve_endereco_normalizado(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response($this->respostaViaCep())]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('integracoes.cep', '01310100'))
            ->assertOk()
            ->assertExactJson([
                'cep' => '01310100',
                'logradouro' => 'Avenida Paulista',
                'bairro' => 'Bela Vista',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
            ]);
    }

    public function test_cep_inexistente_devolve_404(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('integracoes.cep', '99999999'))
            ->assertNotFound()
            ->assertJson(['message' => 'CEP não encontrado.']);
    }

    public function test_servico_fora_do_ar_devolve_503_com_aviso(): void
    {
        Http::fake([
            'viacep.com.br/*' => fn () => throw new ConnectionException('Tempo esgotado'),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('integracoes.cep', '01310100'))
            ->assertStatus(503)
            ->assertJson(['message' => 'Serviço de CEP indisponível. Preencha o endereço manualmente.']);
    }

    public function test_segunda_consulta_do_mesmo_cep_usa_o_cache(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response($this->respostaViaCep())]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('integracoes.cep', '01310100'))->assertOk();
        $this->actingAs($user)->getJson(route('integracoes.cep', '01310100'))->assertOk();

        Http::assertSentCount(1);
    }
}
