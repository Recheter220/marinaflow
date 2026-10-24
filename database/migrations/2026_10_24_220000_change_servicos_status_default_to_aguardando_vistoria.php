<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Altera apenas o padrão de novas inserções, sem converter status existentes.
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('status')->default('aguardando_vistoria')->change();
        });
    }

    public function down(): void
    {
        // Restaura o padrão anterior, preservando inclusive registros em aguardando_vistoria.
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('status')->default('em_execucao')->change();
        });
    }
};
