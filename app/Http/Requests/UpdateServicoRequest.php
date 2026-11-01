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
            // Tipo inativo já ligado ao serviço pode ser mantido; ligar um novo é barrado em ServicoService.
            'tipos' => ['sometimes', 'required', 'array', 'min:1'],
            'tipos.*' => ['integer', 'distinct', Rule::exists('tipos_servico', 'id')],
            'data_execucao' => ['sometimes', 'required', 'date_format:Y-m-d'],
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
            'tipos.required' => 'Selecione ao menos um serviço realizado.',
            'tipos.*.exists' => 'Tipo de serviço inexistente.',
            'data_execucao.date_format' => 'Data de execução deve estar no formato AAAA-MM-DD, sem hora.',
            'status.in' => 'Status inválido. Use: '.implode(', ', Servico::STATUS_EDITAVEIS).'.',
        ];
    }
}
