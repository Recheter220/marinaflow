<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('embarcacao_id')->constrained('embarcacoes')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios');
            $table->json('descricao');
            $table->date('data_execucao');
            $table->string('status')->default('em_execucao');
            $table->text('observacao')->nullable();
            // Auditoria: preenchida a partir de Auth::id() no controller.
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
