<?php

namespace Tests;

use App\Models\TipoServico;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
}
