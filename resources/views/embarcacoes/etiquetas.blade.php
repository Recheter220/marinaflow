<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Etiquetas de embarcações</title>
    {{-- RF-09.5 — A4 em grade 2 × 5. Medidas em mm para a folha bater com a impressão. --}}
    <style>
        @page { margin: 10mm 8mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2933;
            margin: 0;
        }

        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }

        table.grade { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grade td.etiqueta {
            width: 50%;
            height: 54mm;
            padding: 0 3mm;
            border: 1px dashed #c8d3df;
            vertical-align: middle;
        }

        table.conteudo { width: 100%; border-collapse: collapse; }
        table.conteudo td { vertical-align: middle; padding: 0; }
        td.qr { width: 44mm; }
        td.qr img { width: 42mm; height: 42mm; }

        .nome { font-size: 12pt; font-weight: bold; margin: 0 0 2mm; word-wrap: break-word; }
        .identificacao { font-size: 10pt; color: #52606d; margin: 0 0 3mm; }
        .marca { font-size: 7pt; color: #9aa5b1; margin: 0; }

        .vazio { padding: 14pt; text-align: center; border: 1px dashed #9fb3c8; color: #52606d; font-size: 10pt; }
    </style>
</head>
<body>
    @forelse ($paginas as $linhas)
        <div class="pagina">
            <table class="grade">
                @foreach ($linhas as $linha)
                    <tr>
                        @foreach ($linha as $etiqueta)
                            <td class="etiqueta">
                                <table class="conteudo">
                                    <tr>
                                        <td class="qr"><img src="{{ $etiqueta['qr'] }}" alt="QR Code de {{ $etiqueta['nome'] }}"></td>
                                        <td>
                                            <p class="nome">{{ $etiqueta['nome'] }}</p>
                                            <p class="identificacao">{{ $etiqueta['identificacao'] }}</p>
                                            <p class="marca">{{ config('app.name') }}</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                        @if ($linha->count() < 2)
                            <td class="etiqueta"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <p class="vazio">Nenhuma embarcação para gerar etiquetas.</p>
    @endforelse
</body>
</html>
