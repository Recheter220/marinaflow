<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nome', 'cargo', 'telefone', 'ativo'])]
class Funcionario extends Model
{
    use HasFactory;

    protected $table = 'funcionarios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function embarcacoes(): HasMany
    {
        return $this->hasMany(Embarcacao::class);
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }

    /** `funcionario_repository::list_ativos`. */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** `funcionario_repository::search`. */
    public function scopeSearch(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        if ($termo === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
                ->orWhere('cargo', 'like', "%{$termo}%");
        });
    }
}
