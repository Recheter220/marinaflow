<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servico_fotos', function (Blueprint $table) {
            $table->id();
            // INV04 — foto não existe sem serviço.
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->string('disk')->default('fotos');
            $table->string('path');
            $table->string('nome_original');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanho_bytes');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['servico_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_fotos');
    }
};
