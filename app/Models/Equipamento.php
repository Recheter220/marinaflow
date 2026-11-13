<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Equipamento testado na vistoria (MEL02). A condição fica em
 * `servico_equipamento`; sem vínculo, o equipamento "não se aplica" ao serviço.
 */
#[Fillable(['nome'])]
class Equipamento extends Model
{
    use HasFactory;

    protected $table = 'equipamentos';

    public const CONDICAO_NAO_SE_APLICA = 'nao_se_aplica';

    public const CONDICAO_COM_DEFEITO = 'com_defeito';

    public const CONDICAO_FUNCIONAMENTO_OK = 'funcionamento_ok';

    /** Valores aceitos no payload. */
    public const CONDICOES = [
        self::CONDICAO_NAO_SE_APLICA,
        self::CONDICAO_COM_DEFEITO,
        self::CONDICAO_FUNCIONAMENTO_OK,
    ];

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'servico_equipamento')->withPivot('condicao');
    }
}
