<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MEL02 — condição de cada equipamento testado no serviço. Só "com defeito" e
 * "funcionamento OK" viram linha em `servico_equipamento`; a falta de vínculo é
 * "não se aplica", o que mantém os serviços antigos válidos sem backfill.
 */
return new class extends Migration
{
    /** Lista da MEL02, na ordem em que aparece no formulário. */
    private const EQUIPAMENTOS = [
        'Buzina',
        'Guincho da âncora',
        'Motor',
        'Luzes de navegação',
        'Rádio de som',
        'Rádio VHF',
    ];

    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->timestamps();
        });

        Schema::create('servico_equipamento', function (Blueprint $table) {
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            // Apagar um equipamento reescreveria o histórico das vistorias.
            $table->foreignId('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('condicao');
            $table->primary(['servico_id', 'equipamento_id']);
        });

        $agora = now();

        DB::table('equipamentos')->insert(array_map(
            fn (string $nome): array => ['nome' => $nome, 'created_at' => $agora, 'updated_at' => $agora],
            self::EQUIPAMENTOS,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_equipamento');
        Schema::dropIfExists('equipamentos');
    }
};
