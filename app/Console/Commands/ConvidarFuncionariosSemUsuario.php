<?php

namespace App\Console\Commands;

use App\Models\Funcionario;
use App\Services\UserService;
use Illuminate\Console\Command;

/**
 * Porte de `auth_service::gerar_usuarios_para_funcionarios`, que no app Tauri era
 * o comando `cmd_gerar_usuarios_para_funcionarios` — sem nenhum chamador no
 * frontend, por isso vira comando de console em vez de rota web.
 *
 * Diferença relevante: o original sintetizava um login `func{id}` com a senha
 * fixa `123456`. Isso não tem tradução no modelo por e-mail — cada funcionário
 * precisa de um endereço real e único. O comando portanto exige que o e-mail
 * seja informado, funcionário a funcionário, e dispara o convite normal.
 */
class ConvidarFuncionariosSemUsuario extends Command
{
    protected $signature = 'marinaflow:convidar-funcionarios
                            {--dry-run : Apenas lista quem está sem acesso, sem enviar convites}';

    protected $description = 'Envia convite de acesso aos funcionários que ainda não possuem usuário';

    public function handle(UserService $users): int
    {
        $semUsuario = Funcionario::query()
            ->whereDoesntHave('user')
            ->orderBy('nome')
            ->get();

        if ($semUsuario->isEmpty()) {
            $this->info('Todos os funcionários já possuem usuário.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nome', 'Cargo'],
            $semUsuario->map(fn (Funcionario $f) => [$f->id, $f->nome, $f->cargo ?? '-'])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment("{$semUsuario->count()} funcionário(s) sem acesso.");

            return self::SUCCESS;
        }

        $convidados = 0;

        foreach ($semUsuario as $funcionario) {
            $email = $this->ask("E-mail de {$funcionario->nome} (deixe vazio para pular)");

            if (blank($email)) {
                $this->line("  ↷ {$funcionario->nome} ignorado.");

                continue;
            }

            $validator = validator(
                ['email' => $email],
                ['email' => ['required', 'email', 'unique:users,email']],
            );

            if ($validator->fails()) {
                $this->error('  '.$validator->errors()->first('email'));

                continue;
            }

            $users->criar([
                'name' => $funcionario->nome,
                'email' => $email,
                'role' => 'funcionario',
                'funcionario_id' => $funcionario->id,
            ]);

            $this->info("  ✓ Convite enviado para {$email}");
            $convidados++;
        }

        $this->newLine();
        $this->info("{$convidados} convite(s) enviado(s).");

        return self::SUCCESS;
    }
}
