<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'embarcacao_id',
    'funcionario_id',
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

    public const STATUS_AGUARDANDO_VISTORIA = 'aguardando_vistoria';

    public const STATUS_CONCLUIDO = 'concluido';

    /** Rótulos de `Historico.jsx`, para o que sai do servidor (relatório). */
    public const STATUS_ROTULOS = [
        self::STATUS_EM_EXECUCAO => 'Em Execução',
        self::STATUS_AGUARDANDO_VISTORIA => 'Aguardando Vistoria',
        self::STATUS_CONCLUIDO => 'Concluído',
    ];

    /** Valores aceitos no payload; as transições são validadas em ServicoService. */
    public const STATUS_EDITAVEIS = [
        self::STATUS_EM_EXECUCAO,
        self::STATUS_AGUARDANDO_VISTORIA,
        self::STATUS_CONCLUIDO,
    ];

    /**
     * Atividade executada da verificação (MEL02) => tipo do catálogo que a
     * representa. A escolha vira o vínculo com esse tipo em `servico_tipo_servico`,
     * e o serviço continua contando no relatório e na ficha da embarcação.
     */
    public const ATIVIDADES = [
        'lavagem' => 'Lavagem',
        'limpeza' => 'Limpeza',
    ];

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

    /** Itens do serviço (D-04). */
    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(TipoServico::class, 'servico_tipo_servico');
    }

    /** Condições da vistoria (MEL02); equipamento sem vínculo "não se aplica". */
    public function equipamentos(): BelongsToMany
    {
        return $this->belongsToMany(Equipamento::class, 'servico_equipamento')->withPivot('condicao');
    }

    /**
     * Atividade lida dos tipos já carregados. Registro antigo com as duas, ou
     * com nenhuma, fica sem atividade em vez de ganhar uma por palpite.
     */
    public function atividadeExecutada(): ?string
    {
        $atividades = array_values(array_intersect(Servico::ATIVIDADES, $this->tipos->pluck('nome')->all()));

        return count($atividades) === 1 ? array_search($atividades[0], Servico::ATIVIDADES, true) : null;
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ServicoFoto::class)->orderBy('ordem')->orderBy('id');
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
