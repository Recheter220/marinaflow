# MarinaFlow — Especificações de desenvolvimento

Os nove itens do `TODO.md` abertos em histórias especificadas para implementação,
organizadas em quatro ondas. Cada arquivo `MF-XX.md` é uma spec fechada: contexto,
requisitos funcionais numerados, contrato técnico, critérios de aceite verificáveis
e tarefas na ordem de execução.

**12 histórias · 80 pontos · ≈ 4 sprints de 2 semanas.**

## Como usar

A spec é o artefato de entrada da implementação: ninguém abre um editor antes de os
critérios de aceite estarem fechados com o PO. Cada arquivo vira uma issue; cada
tarefa numerada vira um commit (ou um punhado deles) na ordem em que está listada,
porque a ordem carrega a dependência técnica.

A ordem das ondas não é preferência. A Onda 0 destrava as demais: fotos dependem de
armazenamento em nuvem, a ficha da embarcação depende de fotos, e as etiquetas com QR
dependem do motor de PDF.

### Onda 0 · Fundação — 16 pt

Rede de segurança e banco de produção. Nenhuma entrega visível ao usuário — e é
justamente por isso que costuma ser adiada e depois cobrada em dobro.

| Spec | História | Pontos |
|---|---|---|
| [MF-01](MF-01.md) | Suíte de testes de frontend e pipeline de CI | 8 |
| [MF-02](MF-02.md) | Migração de SQLite para PostgreSQL | 5 |
| [MF-03](MF-03.md) | Disco de arquivos em nuvem compatível com S3 | 3 |

### Onda 1 · Domínio — 16 pt

O serviço deixa de ser uma lista de caixas marcadas e passa a ter foto, e cada
embarcação ganha uma ficha que conta a vida do casco.

| Spec | História | Pontos |
|---|---|---|
| [MF-04](MF-04.md) | Fotos do serviço executado | 8 |
| ~~MF-05~~ | ~~Peças aplicadas~~ — [cancelada](MF-05.md) | — |
| [MF-06](MF-06.md) | Ficha da embarcação | 8 |

### Onda 2 · Saídas — 26 pt

Relatório que o cliente recebe, painel que o gestor abre de manhã e a etiqueta com
QR que fica colada no casco.

| Spec | História | Pontos |
|---|---|---|
| [MF-07](MF-07.md) | Relatório mensal em PDF | 8 |
| [MF-08](MF-08.md) | Dashboard gerencial | 13 |
| [MF-09](MF-09.md) | QR Code da embarcação | 5 |

### Onda 3 · Integração e go-live — 22 pt

Serviços externos, conformidade de acessibilidade e a subida para produção.

| Spec | História | Pontos |
|---|---|---|
| [MF-10](MF-10.md) | Endereço com ViaCEP | 3 |
| [MF-11](MF-11.md) | Condição do tempo | 3 |
| [MF-12](MF-12.md) | Acessibilidade WCAG 2.2 AA | 8 |
| [MF-13](MF-13.md) | Deploy em nuvem — *em produção, itens residuais* | 8 |

## Definição de pronto

Vale para todas as histórias:

- Entregue por pull request de uma branch `feature/MF-XX`, com commits no padrão
  Conventional Commits — **commit direto na `main` é proibido**. Ver
  [Fluxo de trabalho no Git](../../README.md#fluxo-de-trabalho-no-git).
- Teste de recurso cobrindo o caminho feliz e as falhas de RBAC e validação.
- `vendor/bin/pint --dirty` limpo e `php artisan test` verde.
- Visibilidade por papel respeitada via `scopeForUser` — funcionário nunca enxerga dado alheio.
- Textos de interface e mensagens de validação em pt-BR.
- Migration com `down()` funcional; factory e seeder para todo modelo novo.
- Nenhuma dependência nova fora das já aprovadas em D-01.
- Página nova nasce acessível (rótulo, foco, contraste) — não espera a MF-12.

## Convenções que toda tarefa segue

Herdadas do código existente. Quem abrir um PR fora delas leva revisão pedindo
alinhamento — a base é pequena e consistente, e vale mantê-la assim.

| Camada | Convenção |
|---|---|
| Modelos | Atributo `#[Fillable([...])]`, `$table` explícito quando a pluralização falha, `casts()` como método, scopes de consulta (`forUser`, `search`, `ativos`) no modelo. |
| Validação | Form Request por ação (`Store*`/`Update*`) com `messages()` em pt-BR. Nada de validação inline no controller. |
| Regra de negócio | `app/Services/*Service.php`. Invariantes e RBAC vivem lá, nunca no controller — ver `ServicoService` (INV01–INV03). |
| Autorização | Middleware `auth` + `ativo` em tudo; grupo `admin` para escrita administrativa; `AccessDeniedHttpException` para negativa de papel. |
| Rotas | URLs em português (`/embarcacoes`, `/historico`), nomes com ponto (`servicos.index`), sempre `route()` no front via Ziggy. |
| Controllers | `Inertia::render('NomeDaPagina', [...])` com props já achatadas em array; redirect com `back()->with('success', ...)`. |
| Páginas React | Arquivo plano em `resources/js/pages`, `Pagina.layout = (page) => <AppLayout>{page}</AppLayout>`, Mantine + Tabler, `notifications.show` para flash. |
| Testes | `php artisan make:test --phpunit`, feature test em `tests/Feature`, factories para montar dado. |

## Decisões técnicas

Todas fechadas, exceto onde indicado. Nenhuma spec está bloqueada esperando decisão.

| ID | Questão | Resolução | Afeta |
|---|---|---|---|
| D-01 | Quais dependências novas entram? | **Aprovadas:** `league/flysystem-aws-s3-v3` (MF-03), `vitest` + `@testing-library/react` + `@testing-library/jest-dom` + `@testing-library/user-event` + `jsdom` (MF-01), `barryvdh/laravel-dompdf` (MF-07), `bacon/bacon-qr-code` (MF-09). Nenhuma tem substituto dentro do que já está instalado. Qualquer outra exige aprovação nova. | MF-01, MF-03, MF-07, MF-09 |
| D-02 | Arrastar-e-soltar para fotos: `@mantine/dropzone` ou `FileInput`? | **`FileInput`**, que já vem no `@mantine/core`. Dependência nova por um ganho de ergonomia que ninguém mediu não se paga. Reabrir se a equipe do píer reclamar do fluxo no celular. | MF-04 |
| D-03 | Motor de PDF: DomPDF ou Browsershot? | **DomPDF.** Deixou de ser preferência e virou restrição: o runtime da Vercel não tem Chromium, então Browsershot e `spatie/laravel-pdf` são impossíveis na plataforma escolhida. O `gd` está presente, confirmado em produção, então imagem dentro do PDF funciona. | MF-07, MF-09 |
| D-04 | Catálogo de serviços: lista fixa em `utils/servicos.js` ou tabela? | **Vira tabela.** Com a MF-05 cancelada, é a única fonte de agregação que resta. Contar tipo de serviço sobre o texto livre de `descricao` quebra a série histórica no dia em que alguém renomear um item. Custo: migration da tabela de tipos, pivot, e backfill dos 8 itens que hoje estão no arquivo. **Única decisão aqui que aumenta escopo — precisa de confirmação do PO.** Se o PO adiar, as RFs de contagem por tipo passam a ser aproximações e têm de ser rotuladas como tal na tela e no PDF. | MF-07, MF-08 |
| D-05 | ViaCEP vale para o funcionário ou também para o cliente responsável? | **Só funcionário.** `cliente_responsavel` é campo de texto solto hoje; quando virar entidade própria, o endereço vai junto nessa hora. | MF-10 |
| D-06 | Plataforma de hospedagem | **Vercel + Neon**, ambos em São Paulo. O monólito inteiro roda como função PHP via runtime `vercel-php` — não há frontend separado para hospedar, que era a premissa errada do `TODO.md`. Em produção desde 30/09/2026. | MF-13 |
| D-07 | Migrar o e-mail do Gmail para um relay transacional (Brevo)? | **Pendente de condição externa:** exige domínio próprio. Hoje é Gmail + App Password, escolhido por alinhamento SPF/DKIM — sem domínio, enviar como `@gmail.com` por relay de terceiro é descartado ou marcado como spam em silêncio, e o convite de senha é o único e-mail que, se não chegar, impede o primeiro acesso. Limites que motivam a troca: 500 envios/dia, conta pessoal como remetente, nenhum log de entrega, nenhum webhook de bounce. | MF-13 |
| D-08 | `ArquivoService` encapsulando o Storage vale a camada? | **Mantido.** `Storage::disk('fotos')->temporaryUrl()` resolveria direto dentro do serviço de domínio, mas o wrapper tem valor didático para o time e sustenta o acordo de que controller nenhum fala com `Storage`. | MF-03, MF-04 |
| D-09 | Qual provedor compatível com S3? | **Cloudflare R2.** Egresso zero, e foto de serviço é vista e revista; 10 GB no gratuito (≈ 3.300 fotos de 3 MB, mais com redimensionamento); edge com presença em São Paulo, o que importa porque o navegador baixa a imagem direto do bucket, não pela função. `AWS_DEFAULT_REGION` tem de ser `auto` — é a única região que o R2 aceita. | MF-03, MF-04 |
| D-10 | Controle de peças | **Cancelado por especificação do usuário da marina.** O sistema controla serviços e fotos. Sai o catálogo de peças e sai o vínculo peça-serviço; os campos de custo derivados foram removidos de MF-06, MF-07 e MF-08. Ver [MF-05](MF-05.md). | MF-05 e dependentes |

## Restrições da plataforma que toda spec herda

Decididas na MF-13 e já em produção. Quem implementar qualquer história precisa
saber disto antes de escrever a primeira linha:

- **Não existe processo longo.** `QUEUE_CONNECTION=sync`: o que for despachado roda
  dentro da requisição, com teto de 30 s. O primeiro job de verdade — o PDF
  assíncrono cogitado na MF-07 — reabre a decisão de plataforma.
- **O filesystem é somente-leitura fora de `/tmp`**, e `/tmp` morre com a invocação.
  Nada de gravar arquivo fora do disco S3. É por isso que a MF-03 é pré-requisito
  absoluto da MF-04.
- **Extensões disponíveis** (confirmadas em produção): `gd` com JPEG/PNG/WebP, `exif`,
  `zip`, `intl`, `pdo_pgsql`, `fileinfo`. **Não há `imagick`.**
- **Migração não roda no deploy.** A Vercel não tem gancho de release; migration é
  passo manual, pelo host direto do Neon, nunca pelo pooler.
- **Cron roda no máximo 1×/dia** no plano Hobby, com garantia apenas da hora.

## Riscos técnicos

### Alto

- **Agregação por tipo de serviço sobre texto livre.** Relatório e painel contam itens
  de um array de strings; renomear um item quebra a série histórica. Mitigado por D-04,
  que precisa do aval do PO antes da Onda 2.
- **Vazamento de arquivo.** Bucket privado com URL assinada é obrigatório: foto de
  embarcação de cliente não pode ficar pública por descuido de configuração.
- **Divergência de dialeto entre teste e produção.** A suíte roda em SQLite e produção
  é PostgreSQL. O caso conhecido (`like` sensível a caixa) está corrigido e coberto,
  mas collation, `GROUP BY` estrito e comparação de datas seguem invisíveis até a
  RF-01.7 rodar o CI contra Postgres.

### Médio

- **Tempo de geração do PDF.** Síncrono até 500 serviços; acima disso, fila e
  notificação — medir na MF-07 antes de assumir. Com `sync` em produção, o teto real
  são os 30 s da função.
- **Cota da OpenWeather.** Cache de 30 minutos mantém o uso previsível; sem cache,
  cada abertura de painel vira uma chamada.
- **Cinco migrations novas** (fotos, `concluido_em`, ULID, tipos de serviço e pivot).
  Rodar todas em ordem contra base com dado real antes de produção.
- **Regressão de acessibilidade.** Sem a regra de lint e os testes com axe da MF-12,
  cada tela nova reintroduz os mesmos problemas.
