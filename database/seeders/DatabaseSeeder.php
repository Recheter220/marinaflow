<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Admin padrão — equivalente ao seed de `db/migrations.rs`.
     *
     * Diferente dos demais usuários, este já nasce com senha definida
     * (`primeiro_acesso: false`), para que sempre exista uma conta capaz de
     * entrar no sistema sem depender do e-mail de convite.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@marinaflow.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'role' => User::ROLE_ADMIN,
                'primeiro_acesso' => false,
                'ativo' => true,
            ],
        );
    }
}
