<?php

namespace Tests\Feature;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use Tests\TestCase;

/**
 * O `like` do SQL é case-insensitive no SQLite e case-sensitive no PostgreSQL.
 * Como produção passa a rodar no Neon, buscar "vento" tem de continuar
 * encontrando "Vento Sul" — o que exige `ilike`. Os testes compilam o SQL contra
 * o grammar do Postgres, sem abrir conexão, porque é a diferença de dialeto que
 * importa, e ela não aparece rodando a suíte no SQLite.
 */
class EscopoBuscaTest extends TestCase
{
    public function test_busca_de_embarcacao_usa_ilike_no_postgres(): void
    {
        $sql = Embarcacao::on('pgsql')->search('vento')->toSql();

        $this->assertStringContainsString('ilike', $sql);
        $this->assertStringNotContainsString(' like ', $sql);
    }

    public function test_busca_de_funcionario_usa_ilike_no_postgres(): void
    {
        $sql = Funcionario::on('pgsql')->search('ana')->toSql();

        $this->assertStringContainsString('ilike', $sql);
        $this->assertStringNotContainsString(' like ', $sql);
    }
}
