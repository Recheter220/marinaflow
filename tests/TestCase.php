<?php

namespace Tests;

use App\Models\TipoServico;
use App\Services\VerificacaoItens;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A suíte PHP não depende do build do frontend: sem isso, a página Inertia
     * exige `public/build/manifest.json`, que não existe num clone limpo (CI).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Ids do catálogo semeado pela migration da D-04.
     *
     * @return array<int, int>
     */
    protected function idsDosTipos(string ...$nomes): array
    {
        return array_map(
            fn (string $nome): int => TipoServico::query()->where('nome', $nome)->value('id'),
            $nomes,
        );
    }

    protected function verificacaoValida(string $atividade = 'limpeza'): array
    {
        return [
            'equipamentos' => array_fill_keys(array_keys(VerificacaoItens::EQUIPAMENTOS), 'funcionamento_ok'),
            'atividade' => $atividade,
        ];
    }
}
