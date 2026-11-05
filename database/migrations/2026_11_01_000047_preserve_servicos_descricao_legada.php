<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Arquiva o texto sem normalização antes da remoção de descricao em develop. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->text('descricao_legada')->nullable();
        });

        if (Schema::hasColumn('servicos', 'descricao')) {
            DB::table('servicos')->update(['descricao_legada' => DB::raw('descricao')]);
        }
    }

    public function down(): void
    {
        if (DB::table('servicos')->whereNotNull('descricao_legada')->exists()) {
            throw new RuntimeException('Rollback bloqueado: remover descricao_legada eliminaria dados preservados. Use recuperação revisada com backup.');
        }

        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('descricao_legada');
        });
    }
};
