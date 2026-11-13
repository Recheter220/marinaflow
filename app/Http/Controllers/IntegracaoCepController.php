<?php

namespace App\Http\Controllers;

use App\Services\CepService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IntegracaoCepController extends Controller
{
    public function show(string $cep, CepService $cepService): JsonResponse
    {
        try {
            $endereco = $cepService->consultar($cep);
        } catch (HttpClientException $e) {
            Log::warning('Consulta de CEP indisponível', [
                'cep' => $cep,
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Serviço de CEP indisponível. Preencha o endereço manualmente.',
            ], 503);
        }

        if ($endereco === null) {
            return response()->json(['message' => 'CEP não encontrado.'], 404);
        }

        return response()->json($endereco);
    }
}
