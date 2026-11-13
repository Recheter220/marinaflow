<?php

namespace App\Http\Controllers;

use App\Http\Requests\EtiquetasRequest;
use App\Models\Embarcacao;
use App\Services\FichaEmbarcacaoService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** QR Code da embarcação e folha de etiquetas (MF-09). */
class EmbarcacaoQrCodeController extends Controller
{
    /** Grade 2 × 5 da folha A4. */
    public const ETIQUETAS_POR_PAGINA = 10;

    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly FichaEmbarcacaoService $ficha,
    ) {}

    /**
     * RF-09.2 / RF-09.7 — destino do QR. A busca já passa pela regra de
     * visibilidade: sem ela, o redirecionamento revelaria o `id` sequencial de
     * uma embarcação que quem escaneou não pode ver.
     */
    public function redirecionar(Request $request, string $ulid): RedirectResponse
    {
        $embarcacao = Embarcacao::query()
            ->forUser($request->user())
            ->where('ulid', Str::lower($ulid))
            ->firstOrFail();

        return redirect()->route('embarcacoes.show', $embarcacao);
    }

    /** RF-09.4 — o SVG do modal; `download=1` entrega como anexo. */
    public function svg(Request $request, int $id): HttpResponse
    {
        $embarcacao = $this->ficha->buscar($id, $request->user());

        $headers = ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600'];

        if ($request->boolean('download')) {
            $arquivo = 'qrcode-'.Str::slug($embarcacao->identificacao ?: $embarcacao->nome).'.svg';
            $headers['Content-Disposition'] = "attachment; filename=\"{$arquivo}\"";
        }

        return response($this->qrCodes->paraEmbarcacao($embarcacao), 200, $headers);
    }

    /** RF-09.5 — folha A4 de etiquetas, das ativas ou da seleção. */
    public function etiquetas(EtiquetasRequest $request): HttpResponse
    {
        $selecao = $request->validated('embarcacoes', []);

        $embarcacoes = Embarcacao::query()
            ->when(
                $selecao === [],
                fn ($query) => $query->where('status', 'ativa'),
                fn ($query) => $query->whereIn('id', $selecao),
            )
            ->orderBy('nome')
            ->get(['id', 'ulid', 'nome', 'identificacao']);

        $paginas = $embarcacoes
            ->map(fn (Embarcacao $e): array => [
                'nome' => $e->nome,
                'identificacao' => $e->identificacao,
                'qr' => $this->qrCodes->dataUri($e),
            ])
            ->chunk(self::ETIQUETAS_POR_PAGINA)
            ->map(fn ($pagina) => $pagina->values()->chunk(2));

        return Pdf::setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4')
            ->loadView('embarcacoes.etiquetas', ['paginas' => $paginas])
            ->stream('etiquetas-embarcacoes.pdf');
    }
}
