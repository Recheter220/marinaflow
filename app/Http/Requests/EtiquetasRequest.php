<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-09.5 — seleção da folha de etiquetas. Sem seleção, a folha leva todas as
 * embarcações ativas. O acesso de administrador é do middleware `admin`.
 */
class EtiquetasRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'embarcacoes' => ['sometimes', 'array'],
            'embarcacoes.*' => ['integer', 'distinct', Rule::exists('embarcacoes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'embarcacoes.array' => 'Seleção de embarcações inválida.',
            'embarcacoes.*.integer' => 'Seleção de embarcações inválida.',
            'embarcacoes.*.distinct' => 'A mesma embarcação foi selecionada mais de uma vez.',
            'embarcacoes.*.exists' => 'Embarcação não encontrada.',
        ];
    }
}
