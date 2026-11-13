<?php

namespace App\Http\Requests;

use App\Models\Equipamento;
use App\Models\Servico;
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
            // A verificação substitui os serviços realizados (MEL02); `tipos` não entra
            // em registro novo. Equipamento fora do payload "não se aplica".
            'atividade' => ['required', Rule::in(array_keys(Servico::ATIVIDADES))],
            'equipamentos' => ['sometimes', 'array:'.Equipamento::query()->pluck('id')->implode(',')],
            'equipamentos.*' => ['required', Rule::in(Equipamento::CONDICOES)],
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
            'atividade.required' => 'Selecione a atividade executada.',
            'atividade.in' => 'Atividade executada inválida.',
            'equipamentos.array' => 'Equipamento inexistente na verificação.',
            'equipamentos.*.in' => 'Condição do equipamento inválida.',
            'data_execucao.required' => 'Data de execução é obrigatória.',
            'data_execucao.date_format' => 'Data de execução deve estar no formato AAAA-MM-DD, sem hora.',
        ];
    }
}
