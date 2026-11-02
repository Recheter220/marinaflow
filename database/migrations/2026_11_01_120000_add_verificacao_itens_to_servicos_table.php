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
            $table->json('verificacao_itens')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('servicos')->whereNotNull('verificacao_itens')->exists()) {
            throw new RuntimeException('Rollback bloqueado: remover verificacao_itens eliminaria dados preservados. Use recuperação revisada com backup.');
        }

        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('verificacao_itens');
        });
    }
};
