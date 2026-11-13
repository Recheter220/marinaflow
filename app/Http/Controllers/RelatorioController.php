<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelatorioServicosRequest;
use App\Models\Embarcacao;
use App\Models\Funcionario;
use App\Services\RelatorioServicoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Relatório mensal de serviços em PDF (MF-07). */
class RelatorioController extends Controller
{
    public function __construct(private readonly RelatorioServicoService $relatorios) {}

    /** Tela de filtros (`Relatorios.jsx`). */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Relatorios', [
            'embarcacoes' => Embarcacao::query()
                ->forUser($user)
                ->orderBy('nome')
                ->get(['id', 'nome', 'identificacao']),
            // Inativos entram: o relatório olha para trás, e quem saiu também executou serviço.
            'funcionarios' => $user->isAdmin()
                ? Funcionario::query()->orderBy('nome')->get(['id', 'nome', 'ativo'])
                : [],
        ]);
    }

    /** O arquivo em si; `formato=html` devolve a mesma view sem passar pelo DomPDF. */
    public function servicos(RelatorioServicosRequest $request): HttpResponse
    {
        $dados = $this->relatorios->montar($request->validated(), $request->user());

        if ($request->validated('formato') === 'html') {
            return response()->view('relatorios.servicos', $dados);
        }

        // Só os glifos usados vão para o arquivo: 1,2 MB cai para dezenas de KB, e o PDF vai por e-mail.
        $pdf = Pdf::setOption('isFontSubsettingEnabled', true)->loadView('relatorios.servicos', $dados);
        $pdf->render();

        // RF-07.5 — o total de páginas só existe depois do render, por isso o rodapé vai no canvas.
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 112,
            $canvas->get_height() - 34,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'),
            8,
            [0.32, 0.38, 0.43],
        );

        return $pdf->download("{$dados['arquivo']}.pdf");
    }
}
