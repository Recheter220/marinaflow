<?php

namespace App\Http\Requests;

use App\Models\Servico;
use App\Models\ServicoFoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * RF-04.1 e RF-04.2. A autoria (RF-04.5) fica em `ServicoFotoService`.
 *
 * O erro de cada arquivo sai na chave `fotos.N`, para a tela apontar qual foi
 * recusado sem descartar os demais (RF-04.8).
 */
class StoreServicoFotoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fotos' => ['required', 'array', 'min:1', 'max:10'],
            'fotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fotos.required' => 'Selecione ao menos uma foto.',
            'fotos.max' => 'Envie no máximo 10 fotos por vez.',
            'fotos.*.file' => 'O envio da foto falhou. Tente novamente.',
            'fotos.*.mimetypes' => 'Formato não aceito: envie JPEG, PNG ou WebP.',
            'fotos.*.max' => 'A foto excede o limite de 10 MB.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Servico $servico */
                $servico = $this->route('servico');
                $existentes = $servico->fotos()->count();
                $restantes = ServicoFoto::LIMITE_POR_SERVICO - $existentes;

                if ($restantes <= 0) {
                    $validator->errors()->add('fotos', 'Este serviço já atingiu o limite de '.ServicoFoto::LIMITE_POR_SERVICO.' fotos.');
                } elseif (count($this->file('fotos', [])) > $restantes) {
                    $validator->errors()->add('fotos', "Este serviço aceita só mais {$restantes} foto(s).");
                }
            },
        ];
    }
}
