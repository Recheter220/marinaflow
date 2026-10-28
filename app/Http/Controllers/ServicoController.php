<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicoRequest;
use App\Http\Requests\UpdateServicoRequest;
use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Services\ArquivoService;
use App\Services\ServicoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** `commands/servico_commands.rs`. */
class ServicoController extends Controller
{
    public function __construct(
        private readonly ServicoService $servicos,
        private readonly ArquivoService $arquivos,
    ) {}

    /** Formulário de registro (`RegistrarServico.jsx`). */
    public function create(Request $request): Response
    {
        return Inertia::render('RegistrarServico', [
            'embarcacoes' => Embarcacao::query()
                ->forUser($request->user())
                ->orderBy('nome')
                ->get(['id', 'nome', 'identificacao']),
            'funcionarios' => Funcionario::query()
                ->ativos()
                ->orderBy('nome')
                ->get(['id', 'nome', 'cargo']),
        ]);
    }

    /**
     * `listar_servicos` e `listar_servicos_por_embarcacao` no mesmo índice —
     * o filtro por embarcação é um parâmetro, não outro endpoint.
     */
    public function index(Request $request): Response
    {
        $embarcacaoId = $request->integer('embarcacao_id') ?: null;

        $servicos = Servico::query()
            ->forUser($request->user())
            ->when($embarcacaoId, fn ($q) => $q->where('embarcacao_id', $embarcacaoId))
            ->with(['embarcacao:id,nome,identificacao', 'funcionario:id,nome', 'fotos'])
            ->latest('data_execucao')
            ->latest('id')
            ->get()
            ->map(fn (Servico $s): array => [
                'id' => $s->id,
                'embarcacao_id' => $s->embarcacao_id,
                'embarcacao_nome' => $s->embarcacao?->nome,
                'funcionario_id' => $s->funcionario_id,
                'funcionario_nome' => $s->funcionario?->nome,
                'descricao' => $s->descricao,
                'data_execucao' => $s->data_execucao?->toDateString(),
                'status' => $s->status,
                'observacao' => $s->observacao,
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
                'fotos' => $s->fotos->map(fn (ServicoFoto $f): array => [
                    'id' => $f->id,
                    'url' => $this->arquivos->urlTemporaria($f->path),
                    'nome_original' => $f->nome_original,
                ]),
            ]);

        return Inertia::render('Historico', [
            'servicos' => $servicos,
            'embarcacoes' => Embarcacao::query()
                ->forUser($request->user())
                ->orderBy('nome')
                ->get(['id', 'nome', 'identificacao']),
            'filtros' => ['embarcacao_id' => $embarcacaoId],
        ]);
    }

    public function store(StoreServicoRequest $request): RedirectResponse
    {
        $servico = $this->servicos->criar($request->validated(), $request->user());

        // A tela de registro envia as fotos depois, uma por requisição, para este id.
        Inertia::flash('servico_id', $servico->id);

        return back()->with('success', "Serviço registrado e está 'Aguardando Vistoria'.");
    }

    public function update(UpdateServicoRequest $request, Servico $servico): RedirectResponse
    {
        $this->servicos->atualizar($servico, $request->validated(), $request->user());

        return back()->with('success', 'Serviço atualizado.');
    }
}
