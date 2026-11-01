<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `models/servico.rs::CreateServico`.
 *
 * INV01 e INV02 (serviço não existe sem embarcação e sem funcionário) são as
 * regras `exists` abaixo. O RBAC e as demais invariantes ficam em `ServicoService`.
 */
class StoreServicoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'embarcacao_id' => ['required', 'integer', Rule::exists('embarcacoes', 'id')],
            'funcionario_id' => ['required', 'integer', Rule::exists('funcionarios', 'id')],
            'tipos' => ['required', 'array', 'min:1'],
            'tipos.*' => ['integer', 'distinct', Rule::exists('tipos_servico', 'id')->where('ativo', true)],
            'data_execucao' => ['required', 'date'],
            'observacao' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'embarcacao_id.exists' => 'Embarcação selecionada não existe.',
            'funcionario_id.exists' => 'Funcionário selecionado não existe.',
            'tipos.required' => 'Selecione ao menos um serviço realizado.',
            'tipos.*.exists' => 'Tipo de serviço inexistente ou fora de uso.',
            'data_execucao.required' => 'Data de execução é obrigatória.',
        ];
    }
}
