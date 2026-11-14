<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class MeteorologiaService
{
    public function previsao(): array
    {
        $snapshot = Cache::get('meteorologia:v1:dados');
        $fallback = false;

        if (! Cache::has('meteorologia:v1:consulta')) {
            $lock = Cache::lock('meteorologia:v1:lock', 10);

            if ($lock->get()) {
                try {
                    $response = Http::acceptJson()->connectTimeout(2)->timeout(5)
                        ->get(config('meteorologia.url'))->throw();
                    $registros = $this->validar(json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
                    $snapshot = ['registros' => $registros, 'consultado_em' => CarbonImmutable::now()->toIso8601String()];
                    Cache::put('meteorologia:v1:dados', $snapshot, config('meteorologia.retention_seconds'));
                    Cache::put('meteorologia:v1:consulta', 'ok', config('meteorologia.cache_seconds'));
                } catch (ConnectionException|RequestException|JsonException|ValidationException $exception) {
                    Log::warning('Fonte meteorológica indisponível ou inválida.', ['tipo' => $exception::class]);
                    Cache::put('meteorologia:v1:consulta', 'falha', config('meteorologia.failure_seconds'));
                    $fallback = true;
                } finally {
                    $lock->release();
                }
            } else {
                $fallback = true;
            }
        }

        $registros = $snapshot['registros'] ?? [];
        $horaAtual = CarbonImmutable::now(config('meteorologia.timezone'))->format('Y-m-d\TH:00');
        $expirada = $registros !== [] && $registros[array_key_last($registros)]['data_hora'] < $horaAtual;

        return [
            'registros' => $registros,
            'consultado_em' => $snapshot['consultado_em'] ?? null,
            'status' => $registros === [] ? 'indisponivel' : ($expirada ? 'expirada' : (($fallback || Cache::get('meteorologia:v1:consulta') === 'falha') ? 'cache' : 'disponivel')),
        ];
    }

    private function validar(mixed $dados): array
    {
        $rules = [
            'registros' => ['required', 'array', 'list', 'min:1', 'max:24'],
            'registros.*' => ['required', 'array'],
            'registros.*.data_hora' => ['required', 'date_format:Y-m-d\TH:00', 'distinct'],
        ];

        foreach (['temperatura_c', 'chance_chuva_pct', 'volume_chuva_mm', 'visibilidade_m', 'vento_kmh', 'vento_rajada_kmh', 'vento_direcao_graus', 'onda_altura_m', 'onda_periodo_s', 'onda_direcao_graus'] as $campo) {
            $rules['registros.*.'.$campo] = ['present', 'nullable', 'numeric'];
            if ($campo !== 'temperatura_c') {
                $rules['registros.*.'.$campo][] = 'min:0';
            }
            if ($campo === 'chance_chuva_pct') {
                $rules['registros.*.'.$campo][] = 'max:100';
            }
            if (str_ends_with($campo, '_graus')) {
                $rules['registros.*.'.$campo][] = 'max:360';
            }
        }

        $validated = Validator::make(['registros' => $dados], $rules)->validate();
        $registros = collect($validated['registros'])->sortBy('data_hora')->values()->all();

        foreach ($registros as $index => $registro) {
            if ($index > 0 && CarbonImmutable::parse($registros[$index - 1]['data_hora'], config('meteorologia.timezone'))->addHour()->format('Y-m-d\TH:i') !== $registro['data_hora']) {
                throw ValidationException::withMessages(['registros' => 'As horas da previsão devem ser consecutivas.']);
            }
        }

        return $registros;
    }
}
