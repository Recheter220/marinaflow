<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `models/funcionario.rs::UpdateFuncionario`. */
class UpdateFuncionarioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Nome do funcionário é obrigatório.',
        ];
    }
}
