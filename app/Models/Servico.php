<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'embarcacao_id',
    'funcionario_id',
    'descricao',
    'data_execucao',
    'status',
    'observacao',
    'created_by_user_id',
    'updated_by_user_id',
])]
class Servico extends Model
{
    use HasFactory;

    protected $table = 'servicos';

    public const STATUS_EM_EXECUCAO = 'em_execucao';

    public const STATUS_CONCLUIDO = 'concluido';

    /** Os únicos status que uma atualização pode pedir (`pendente` saiu do domínio). */
    public const STATUS_EDITAVEIS = [self::STATUS_EM_EXECUCAO, self::STATUS_CONCLUIDO];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_execucao' => 'date',
        ];
    }

    public function embarcacao(): BelongsTo
    {
        return $this->belongsTo(Embarcacao::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** Mesma regra de visibilidade de Embarcacao::forUser. */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('funcionario_id', $user->funcionario_id);
    }
}
