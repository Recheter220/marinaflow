<?php

namespace App\Services;

use App\Models\Embarcacao;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** RF-09.6 — o QR sai em SVG do próprio servidor, sem serviço externo de imagem. */
class QrCodeService
{
    /** Lado do SVG em px; a escala real fica com quem exibe ou imprime. */
    private const TAMANHO = 320;

    /**
     * SVG do QR que aponta para `/e/{ulid}`. Correção de erro nível Q (25%):
     * a etiqueta fica no píer, sujeita a sol, sal e arranhão.
     */
    public function paraEmbarcacao(Embarcacao $embarcacao): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::TAMANHO), new SvgImageBackEnd));

        return $writer->writeString(
            route('embarcacoes.qr', $embarcacao->ulid),
            Encoder::DEFAULT_BYTE_MODE_ECODING,
            ErrorCorrectionLevel::Q(),
        );
    }

    /** O mesmo SVG como data URI, que é como o DomPDF aceita imagem sem buscar nada remoto. */
    public function dataUri(Embarcacao $embarcacao): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->paraEmbarcacao($embarcacao));
    }
}
