<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de serviços (D-04). Tipo fora de uso é desativado, nunca apagado:
 * sai do formulário, mas continua no histórico e nas contagens do relatório.
 */
#[Fillable(['nome', 'ativo'])]
class TipoServico extends Model
{
    use HasFactory;

    protected $table = 'tipos_servico';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'servico_tipo_servico');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
