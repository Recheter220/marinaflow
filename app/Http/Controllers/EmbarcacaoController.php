<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmbarcacaoRequest;
use App\Http\Requests\UpdateEmbarcacaoRequest;
use App\Models\Embarcacao;
use App\Models\Funcionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** `commands/embarcacao_commands.rs`. */
class EmbarcacaoController extends Controller
{
    /**
     * `listar_embarcacoes` e `buscar_embarcacoes` num único endpoint: a busca do
     * app Tauri era um comando separado, mas aqui é o mesmo índice com um filtro.
     */
    public function index(Request $request): Response
    {
        $termo = $request->string('busca')->toString();

        return Inertia::render('Embarcacoes', [
            'embarcacoes' => Embarcacao::query()
                ->forUser($request->user())
                ->search($termo)
                ->with('funcionario:id,nome')
                ->orderBy('nome')
                ->get()
                ->map(fn (Embarcacao $e): array => [
                    'id' => $e->id,
                    'nome' => $e->nome,
                    'identificacao' => $e->identificacao,
                    'modelo' => $e->modelo,
                    'tipo' => $e->tipo,
                    'comprimento' => $e->comprimento,
                    'ano_fabricacao' => $e->ano_fabricacao,
                    'cliente_responsavel' => $e->cliente_responsavel,
                    'status' => $e->status,
                    'funcionario_id' => $e->funcionario_id,
                    'funcionario_nome' => $e->funcionario?->nome,
                ]),
            'funcionarios' => Funcionario::query()->ativos()->orderBy('nome')->get(['id', 'nome']),
            'filtros' => ['busca' => $termo],
        ]);
    }

    public function store(StoreEmbarcacaoRequest $request): RedirectResponse
    {
        Embarcacao::query()->create($request->validated());

        return back()->with('success', 'Embarcação cadastrada.');
    }

    public function update(UpdateEmbarcacaoRequest $request, Embarcacao $embarcacao): RedirectResponse
    {
        $embarcacao->update($request->validated());

        return back()->with('success', 'Embarcação atualizada.');
    }
}
