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
            'descricao' => ['required', 'array', 'min:1'],
            'descricao.*' => ['required', 'string'],
            'data_execucao' => ['required', 'date_format:Y-m-d'],
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
            'descricao.required' => 'Descrição do serviço é obrigatória.',
            'data_execucao.required' => 'Data de execução é obrigatória.',
            'data_execucao.date_format' => 'Data de execução deve estar no formato AAAA-MM-DD, sem hora.',
        ];
    }
}
