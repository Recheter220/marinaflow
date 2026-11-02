<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-04 — catálogo de serviços deixa de ser lista fixa no front e vira tabela. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_servico', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            // Tipo fora de uso sai do formulário, mas continua contando no histórico.
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('servico_tipo_servico', function (Blueprint $table) {
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            // Tipo é desativado, nunca apagado: apagar reescreveria a série histórica.
            $table->foreignId('tipo_servico_id')->constrained('tipos_servico')->restrictOnDelete();
            $table->primary(['servico_id', 'tipo_servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_tipo_servico');
        Schema::dropIfExists('tipos_servico');
    }
};
