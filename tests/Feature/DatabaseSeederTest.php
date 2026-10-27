<?php

namespace Tests\Feature;

use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A base local tem de ser recriável com um `migrate:fresh --seed`. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambiente_local_ganha_base_de_demonstracao_coerente(): void
    {
        Storage::fake('fotos-local');
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Funcionario::query()->count());
        $this->assertSame(15, Servico::query()->count());
        $this->assertEqualsCanonicalizing(
            Servico::STATUS_EDITAVEIS,
            Servico::query()->distinct()->pluck('status')->all(),
        );

        // Mesma regra do `ServicoService::criar`: o serviço é na embarcação do próprio funcionário.
        Servico::query()->with('embarcacao')->get()->each(
            fn (Servico $s) => $this->assertSame($s->funcionario_id, $s->embarcacao->funcionario_id),
        );

        Servico::query()->with('fotos')->get()->each(function (Servico $s): void {
            $this->assertCount(1, $s->fotos);
            Storage::disk('fotos-local')->assertExists($s->fotos[0]->path);
        });

        $carlos = User::query()->where('email', 'carlos@marinaflow.local')->sole();
        $this->assertTrue(Hash::check(DemonstracaoSeeder::SENHA, $carlos->password));
        $this->assertNotNull($carlos->funcionario_id);
    }

    public function test_rodar_de_novo_nao_duplica_a_demonstracao(): void
    {
        Storage::fake('fotos-local');
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Funcionario::query()->count());
        $this->assertSame(15, Servico::query()->count());
    }

    public function test_fora_do_ambiente_local_so_o_admin_e_criado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Funcionario::query()->count());
    }

    public function test_demonstracao_chamada_direto_fora_do_local_nao_cria_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame(0, Funcionario::query()->count());
        $this->assertSame(0, Servico::query()->count());
    }
}
