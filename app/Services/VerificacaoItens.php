<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VerificacaoItens
{
    public const EQUIPAMENTOS = [
        'buzina' => 'Buzina',
        'guincho_ancora' => 'Guincho da âncora',
        'motor' => 'Motor',
        'luzes_navegacao' => 'Luzes de navegação',
        'radio_som' => 'Rádio de som',
        'radio_vhf' => 'Rádio VHF',
    ];

    public const CONDICOES = ['nao_se_aplica', 'com_defeito', 'funcionamento_ok'];

    public const ATIVIDADES = ['lavagem' => 'Lavagem', 'limpeza' => 'Limpeza'];

    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        $rules = [
            'verificacao_itens' => ['required', 'array:equipamentos,atividade'],
            'verificacao_itens.equipamentos' => ['required', 'array:'.implode(',', array_keys(self::EQUIPAMENTOS))],
            'verificacao_itens.atividade' => ['required', 'string', Rule::in(array_keys(self::ATIVIDADES))],
        ];

        foreach (self::EQUIPAMENTOS as $codigo => $nome) {
            $rules['verificacao_itens.equipamentos.'.$codigo] = ['required', 'string', Rule::in(self::CONDICOES)];
        }

        return $rules;
    }

    /** @param array<string, mixed> $data */
    public static function validate(array $data): void
    {
        Validator::make($data, self::rules())->validate();
    }
}
