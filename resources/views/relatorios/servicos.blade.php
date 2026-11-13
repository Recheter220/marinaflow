<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de serviços — {{ $periodo['por_extenso'] }}</title>
    {{-- CSS embutido: o DomPDF não busca nada remoto (enable_remote desligado). --}}
    <style>
        @page { margin: 1.6cm 1.4cm 2cm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            color: #1f2933;
            margin: 0;
        }

        h1 { font-size: 16pt; margin: 0; color: #0b4f8a; }
        h2 { font-size: 11pt; margin: 18pt 0 6pt; color: #0b4f8a; border-bottom: 1px solid #c8d3df; padding-bottom: 3pt; }
        h3 { font-size: 9.5pt; margin: 0 0 4pt; }

        .cabecalho { border-bottom: 2px solid #0b4f8a; padding-bottom: 8pt; margin-bottom: 4pt; }
        .subtitulo { font-size: 11pt; margin: 2pt 0 8pt; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1pt 0; vertical-align: top; }
        .meta .rotulo { width: 2.6cm; color: #52606d; }

        table.servicos { width: 100%; border-collapse: collapse; }
        table.servicos thead { display: table-header-group; }
        table.servicos th {
            background: #e8eef5;
            text-align: left;
            font-size: 8pt;
            padding: 4pt;
            border-bottom: 1px solid #9fb3c8;
        }
        table.servicos td { padding: 4pt; border-bottom: 1px solid #e1e7ee; vertical-align: top; }
        /* RF-07.5 — uma linha de serviço nunca é cortada entre duas páginas. */
        table.servicos tr { page-break-inside: avoid; }
        .data { white-space: nowrap; width: 1.7cm; }
        .identificacao { color: #52606d; font-size: 7.5pt; }
        .observacao { color: #52606d; font-style: italic; margin-top: 2pt; }

        .vazio { padding: 14pt; text-align: center; border: 1px dashed #9fb3c8; color: #52606d; }

        .total { font-size: 11pt; margin-bottom: 8pt; }
        .blocos { width: 100%; border-collapse: separate; border-spacing: 0 0; }
        .blocos > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 8pt 10pt 0; }
        .contagem { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        .contagem td { padding: 2pt 4pt; border-bottom: 1px solid #e1e7ee; }
        .contagem td.qtd { text-align: right; width: 1.2cm; }
        .nenhum { color: #52606d; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <h1>{{ config('app.name') }}</h1>
        <p class="subtitulo">Relatório de serviços executados — {{ $periodo['por_extenso'] }}</p>

        <table class="meta">
            <tr>
                <td class="rotulo">Período</td>
                <td>{{ \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($periodo['fim'])->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="rotulo">Filtros</td>
                <td>
                    @if ($filtros['embarcacao'] || $filtros['funcionario'])
                        @if ($filtros['embarcacao']) Embarcação: {{ $filtros['embarcacao'] }}@endif
                        @if ($filtros['embarcacao'] && $filtros['funcionario']) · @endif
                        @if ($filtros['funcionario']) Funcionário: {{ $filtros['funcionario'] }}@endif
                    @else
                        Nenhum — todos os serviços do período
                    @endif
                </td>
            </tr>
            <tr>
                <td class="rotulo">Emitido em</td>
                <td>{{ $emissao['em'] }}, por {{ $emissao['por'] }}</td>
            </tr>
        </table>
    </div>

    <h2>Serviços executados</h2>

    @if (count($linhas) === 0)
        <p class="vazio">Nenhum serviço executado no período.</p>
    @else
        <table class="servicos">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Embarcação</th>
                    <th>Executante</th>
                    <th>Serviços</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    <tr>
                        <td class="data">{{ $linha['data'] }}</td>
                        <td>
                            {{ $linha['embarcacao'] }}
                            <div class="identificacao">{{ $linha['identificacao'] }}</div>
                        </td>
                        <td>{{ $linha['funcionario'] }}</td>
                        <td>
                            {{ $linha['tipos'] }}
                            @if ($linha['observacao'])
                                <div class="observacao">Obs.: {{ $linha['observacao'] }}</div>
                            @endif
                        </td>
                        <td>{{ $linha['situacao'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Resumo</h2>

    <p class="total"><strong>Total de serviços:</strong> {{ $resumo['total'] }}</p>

    @php
        $blocos = [
            ['Por situação', $resumo['por_situacao']],
            ['Por tipo de serviço', $resumo['por_tipo']],
            ['Por embarcação', $resumo['por_embarcacao']],
            ['Por funcionário', $resumo['por_funcionario']],
        ];
    @endphp

    <table class="blocos">
        @foreach (array_chunk($blocos, 2) as $par)
            <tr>
                @foreach ($par as [$titulo, $contagem])
                    <td>
                        <h3>{{ $titulo }}</h3>
                        <table class="contagem">
                            @forelse ($contagem as $nome => $quantidade)
                                <tr>
                                    <td>{{ $nome }}</td>
                                    <td class="qtd">{{ $quantidade }}</td>
                                </tr>
                            @empty
                                <tr><td class="nenhum">—</td></tr>
                            @endforelse
                        </table>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</body>
</html>
