<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embarcacoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('identificacao')->unique();
            $table->string('modelo')->nullable();
            $table->string('tipo')->nullable();
            $table->float('comprimento')->nullable();
            $table->integer('ano_fabricacao')->nullable();
            $table->string('cliente_responsavel')->nullable();
            $table->string('status')->default('ativa');
            $table->foreignId('funcionario_id')
                ->nullable()
                ->constrained('funcionarios')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embarcacoes');
    }
};
