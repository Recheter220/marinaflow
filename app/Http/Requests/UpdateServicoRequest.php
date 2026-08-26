<?php

namespace App\Http\Requests;

use App\Models\Servico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `models/servico.rs::UpdateServico` — todos os campos opcionais. */
class UpdateServicoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descricao' => ['sometimes', 'required', 'array', 'min:1'],
            'descricao.*' => ['required', 'string'],
            'data_execucao' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::in(Servico::STATUS_EDITAVEIS)],
            'observacao' => ['nullable', 'string'],
            'funcionario_id' => ['sometimes', 'nullable', 'integer', Rule::exists('funcionarios', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status inválido. Use: '.implode(', ', Servico::STATUS_EDITAVEIS).'.',
        ];
    }
}
