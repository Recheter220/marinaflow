<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->json('tipos_legados')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('servicos')->whereNotNull('tipos_legados')->exists()
            || DB::table('servicos')->whereNotNull('verificacao_itens')->exists()
            || DB::table('servicos')->whereNotNull('descricao_legada')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem dados MEL02 ou históricos preservados. Use recuperação revisada com backup.');
        }

        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('tipos_legados');
        });
    }
};
