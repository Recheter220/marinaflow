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
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Nome do funcionário é obrigatório.',
            'email.unique' => 'Este e-mail já está em uso.',
        ];
    }
}
