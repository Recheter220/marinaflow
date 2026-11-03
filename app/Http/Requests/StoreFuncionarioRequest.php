<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `models/funcionario.rs::CreateFuncionario`, mais um `email` opcional.
 *
 * O app Tauri criava, junto com o funcionário, um usuário com login derivado do
 * nome e senha temporária exibida na tela. Aqui o acesso é opcional e, quando
 * pedido, sai como convite por e-mail.
 */
class StoreFuncionarioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    /**
     * Normaliza o endereço antes de validar: o CEP pode chegar com máscara
     * (01310-100) e a UF em minúscula.
     */
    protected function prepareForValidation(): void
    {
        $dados = [];

        if (is_string($this->cep)) {
            $cep = preg_replace('/\D/', '', $this->cep);
            $dados['cep'] = $cep === '' ? null : $cep;
        }

        if (is_string($this->uf)) {
            $dados['uf'] = strtoupper($this->uf);
        }

        $this->merge($dados);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'cep' => ['nullable', 'string', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Nome do funcionário é obrigatório.',
            'cep.digits' => 'CEP deve ter 8 dígitos.',
            'uf.size' => 'UF deve ter 2 letras.',
            'uf.alpha' => 'UF deve conter apenas letras.',
            'email.unique' => 'Este e-mail já está em uso.',
        ];
    }
}
