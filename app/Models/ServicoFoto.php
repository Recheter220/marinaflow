<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'servico_id',
    'disk',
    'path',
    'nome_original',
    'mime_type',
    'tamanho_bytes',
    'ordem',
    'uploaded_by_user_id',
])]
class ServicoFoto extends Model
{
    use HasFactory;

    protected $table = 'servico_fotos';

    /** RF-04.2 — contando as fotos já existentes, não só as do envio atual. */
    public const LIMITE_POR_SERVICO = 20;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamanho_bytes' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
