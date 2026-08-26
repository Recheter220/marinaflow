<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // `nome` do funcionário quando houver; opcional porque o convite é
            // criado apenas com e-mail + papel.
            $table->string('name')->nullable();
            // Identificador de autenticação — substitui o `login` do app Tauri.
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            // Nulo até o usuário concluir o convite: sem senha não há login.
            $table->string('password')->nullable();
            $table->string('role')->default('funcionario');
            // "convite ainda não concluído" — alimenta o badge "Pendente".
            $table->boolean('primeiro_acesso')->default(true);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
