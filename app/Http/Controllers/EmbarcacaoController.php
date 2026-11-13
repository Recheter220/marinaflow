<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmbarcacaoRequest;
use App\Http\Requests\UpdateEmbarcacaoRequest;
use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Services\FichaEmbarcacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** `commands/embarcacao_commands.rs`. */
class EmbarcacaoController extends Controller
{
    public function __construct(private readonly FichaEmbarcacaoService $ficha) {}

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
                    'ulid' => $e->ulid,
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

    public function show(Request $request, int $id): Response
    {
        $embarcacao = $this->ficha->buscar($id, $request->user());

        return Inertia::render('EmbarcacaoFicha', [
            'embarcacao' => $this->ficha->dados($embarcacao),

            'resumo' => Inertia::defer(fn () => $this->ficha->resumo($embarcacao)),

            'servicos' => Inertia::defer(
                fn () => $this->ficha->linhaDoTempo($embarcacao, (int) $request->query('pagina', 1)),
            )->merge(),

            'fotos' => Inertia::defer(fn () => $this->ficha->galeria($embarcacao)),
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
