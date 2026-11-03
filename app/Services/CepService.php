<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CepService
{
    /**
     * Consulta um CEP no ViaCEP.
     *
     * Devolve o endereço normalizado ou null quando o CEP não existe.
     * Se o serviço estiver fora do ar ou demorar demais, a exceção
     * é lançada para quem chamou tratar (o controller devolve 503).
     */
    public function consultar(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        return Cache::remember("cep:{$cep}", now()->addDays(30), function () use ($cep) {
            $resposta = Http::timeout(3)
                ->retry(2, 200)
                ->get("https://viacep.com.br/ws/{$cep}/json/")
                ->throw();

            $dados = $resposta->json();

            // O ViaCEP responde HTTP 200 com {"erro": true} quando o CEP não existe.
            if (! is_array($dados) || ! empty($dados['erro'])) {
                return null;
            }

            return [
                'cep' => $cep,
                'logradouro' => $dados['logradouro'] ?? '',
                'bairro' => $dados['bairro'] ?? '',
                'cidade' => $dados['localidade'] ?? '',
                'uf' => $dados['uf'] ?? '',
            ];
        });
    }
}
