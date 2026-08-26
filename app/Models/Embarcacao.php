<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome',
    'identificacao',
    'modelo',
    'tipo',
    'comprimento',
    'ano_fabricacao',
    'cliente_responsavel',
    'status',
    'funcionario_id',
])]
class Embarcacao extends Model
{
    use HasFactory;

    /** Eloquent pluralizaria para `embarcacaos`. */
    protected $table = 'embarcacoes';

    public const STATUS = ['ativa', 'inativa', 'em_manutencao'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'comprimento' => 'float',
            'ano_fabricacao' => 'integer',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }

    /**
     * Regra de visibilidade de `embarcacao_service::listar`/`buscar`:
     * admin vê tudo, funcionário vê apenas as embarcações vinculadas a ele.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('funcionario_id', $user->funcionario_id);
    }

    /** `embarcacao_repository::search`. */
    public function scopeSearch(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        if ($termo === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
                ->orWhere('identificacao', 'like', "%{$termo}%")
                ->orWhere('cliente_responsavel', 'like', "%{$termo}%");
        });
    }
}
