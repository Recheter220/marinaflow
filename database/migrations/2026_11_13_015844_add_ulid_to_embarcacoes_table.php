<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RF-09.1 — identificador público da etiqueta. O `id` sequencial convidaria a
 * varrer o acervo a partir de uma etiqueta exposta no píer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        // Query builder, não o modelo: a migration não pode depender de como o modelo estará amanhã.
        DB::table('embarcacoes')
            ->whereNull('ulid')
            ->lazyById()
            ->each(fn (object $embarcacao) => DB::table('embarcacoes')
                ->where('id', $embarcacao->id)
                ->update(['ulid' => Str::lower((string) Str::ulid())]));

        // No SQLite, `change()` recria a tabela; dentro da transação da migration o PRAGMA
        // que desliga as FKs é ignorado e o `drop table` apagaria em cascata os serviços.
        // Lá a coluna fica nula no banco — o `HasUlids` do modelo garante o preenchimento.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('embarcacoes', function (Blueprint $table) {
            $table->ulid('ulid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
