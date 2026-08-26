<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `models/embarcacao.rs::CreateEmbarcacao`. */
class StoreEmbarcacaoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'identificacao' => ['required', 'string', 'max:255', Rule::unique('embarcacoes', 'identificacao')],
            'modelo' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'comprimento' => ['nullable', 'numeric', 'min:0'],
            'ano_fabricacao' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'cliente_responsavel' => ['nullable', 'string', 'max:255'],
            'funcionario_id' => ['nullable', 'integer', Rule::exists('funcionarios', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Nome da embarcação é obrigatório.',
            'identificacao.required' => 'Identificação da embarcação é obrigatória.',
            'identificacao.unique' => 'Já existe uma embarcação com esta identificação.',
        ];
    }
}
