<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-04 — os itens do serviço passam a viver em `servico_tipo_servico`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('descricao');
        });
    }

    /** Volta anulável: o `down()` da migration anterior é quem a preenche. */
    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->json('descricao')->nullable();
        });
    }
};
