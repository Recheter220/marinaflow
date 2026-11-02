<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Período e filtros do relatório de serviços (RF-07.1, RF-07.8). O recorte por
 * papel não é validação: `RelatorioServicoService` aplica `forUser`.
 */
class RelatorioServicosRequest extends FormRequest
{
    /** Um ano bissexto inteiro, contando o primeiro e o último dia. */
    public const MAXIMO_DIAS = 366;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after_or_equal:inicio', 'before_or_equal:'.now()->addDay()->toDateString()],
            'embarcacao_id' => ['nullable', 'integer', Rule::exists('embarcacoes', 'id')],
            'funcionario_id' => ['nullable', 'integer', Rule::exists('funcionarios', 'id')],
            'formato' => ['sometimes', Rule::in(['pdf', 'html'])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['inicio', 'fim'])) {
                    return;
                }

                $dias = Carbon::parse($this->input('inicio'))->diffInDays(Carbon::parse($this->input('fim'))) + 1;

                if ($dias > self::MAXIMO_DIAS) {
                    $validator->errors()->add('fim', 'O período pode ter no máximo '.self::MAXIMO_DIAS.' dias.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inicio.required' => 'Informe a data inicial.',
            'inicio.date' => 'Data inicial inválida.',
            'fim.required' => 'Informe a data final.',
            'fim.date' => 'Data final inválida.',
            'fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'fim.before_or_equal' => 'A data final não pode estar no futuro.',
            'embarcacao_id.exists' => 'Embarcação selecionada não existe.',
            'funcionario_id.exists' => 'Funcionário selecionado não existe.',
            'formato.in' => 'Formato inválido. Use pdf ou html.',
        ];
    }
}
