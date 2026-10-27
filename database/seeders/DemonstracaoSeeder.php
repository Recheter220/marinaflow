<?php

namespace Database\Seeders;

use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Base de demonstração para desenvolvimento: funcionários com login,
 * embarcações vinculadas e serviços em todos os status, com foto.
 *
 * Só roda em `local` e não roda de novo se já houver funcionário cadastrado.
 * Para recriar do zero: `migrate:fresh --seed`.
 */
class DemonstracaoSeeder extends Seeder
{
    /** Senha de todos os funcionários de demonstração. */
    public const SENHA = 'senha123';

    /** Mesmo catálogo de `resources/js/utils/servicos.js`. */
    private const CATALOGO = [
        'Buzina',
        'Guincho da âncora',
        'Lavagem',
        'Limpeza',
        'Luzes de navegação',
        'Motor',
        'Rádio de som',
        'radio VHF',
    ];

    /**
     * Funcionário => [cargo, e-mail de login, embarcações sob responsabilidade].
     *
     * @var array<string, array{0: string, 1: string, 2: array<int, array{0: string, 1: string, 2: string}>}>
     */
    private const EQUIPE = [
        'Carlos Menezes' => ['Mecânico', 'carlos@marinaflow.local', [
            ['Maré Alta', 'BR-1021-SPA', 'lancha'],
            ['Brisa do Sul', 'BR-1187-RJB', 'veleiro'],
        ]],
        'Juliana Prado' => ['Eletricista', 'juliana@marinaflow.local', [
            ['Estrela Guia', 'BR-2245-SPC', 'iate'],
            ['Netuno II', 'BR-2310-SCD', 'lancha'],
        ]],
        'Rafael Souza' => ['Pintor', 'rafael@marinaflow.local', [
            ['Gaivota', 'BR-3302-PRE', 'jet_ski'],
        ]],
    ];

    public function run(): void
    {
        // O `DatabaseSeeder` já filtra, mas `db:seed --class` chama este direto.
        if (! app()->environment('local')) {
            $this->command?->warn('Fora do ambiente local — demonstração ignorada.');

            return;
        }

        if (Funcionario::query()->exists()) {
            $this->command?->warn('Já há funcionários cadastrados — demonstração ignorada.');

            return;
        }

        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();

        foreach (self::EQUIPE as $nome => [$cargo, $email, $embarcacoes]) {
            $funcionario = Funcionario::factory()->create(['nome' => $nome, 'cargo' => $cargo]);
            $user = User::factory()->create([
                'name' => $nome,
                'email' => $email,
                'password' => self::SENHA,
                'funcionario_id' => $funcionario->id,
            ]);

            foreach ($embarcacoes as [$nomeEmbarcacao, $identificacao, $tipo]) {
                $embarcacao = Embarcacao::factory()->doFuncionario($funcionario)->create([
                    'nome' => $nomeEmbarcacao,
                    'identificacao' => $identificacao,
                    'tipo' => $tipo,
                ]);

                $this->servicosDe($embarcacao, $funcionario, $user, $admin);
            }
        }

        // Inativo: aparece no cadastro, mas não pode receber serviço novo.
        Funcionario::factory()->inativo()->create(['nome' => 'Paulo Antunes', 'cargo' => 'Mecânico']);

        // Sem responsável: só o admin enxerga.
        Embarcacao::factory()->create(['nome' => 'Sereia', 'identificacao' => 'BR-4410-SPF', 'tipo' => 'veleiro']);

        $this->call(ServicoFotoSeeder::class);
    }

    /**
     * Do mais antigo ao mais recente: um concluído pelo admin, um `em_execucao`
     * de antes da MEL03 (registro legado) e um aguardando vistoria, para o
     * histórico ter os três status e os dois perfis de autoria.
     */
    private function servicosDe(Embarcacao $embarcacao, Funcionario $funcionario, User $user, User $admin): void
    {
        $porDiasAtras = [
            120 => Servico::STATUS_CONCLUIDO,
            45 => Servico::STATUS_EM_EXECUCAO,
            3 => Servico::STATUS_AGUARDANDO_VISTORIA,
        ];

        foreach ($porDiasAtras as $diasAtras => $status) {
            $concluido = $status === Servico::STATUS_CONCLUIDO;

            Servico::factory()->create([
                'embarcacao_id' => $embarcacao->id,
                'funcionario_id' => $funcionario->id,
                'descricao' => fake()->randomElements(self::CATALOGO, fake()->numberBetween(1, 3)),
                'data_execucao' => now()->subDays($diasAtras)->toDateString(),
                'status' => $status,
                'observacao' => $status === Servico::STATUS_EM_EXECUCAO ? 'Aguardando peça para finalizar.' : null,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $concluido ? $admin->id : $user->id,
            ]);
        }
    }
}
