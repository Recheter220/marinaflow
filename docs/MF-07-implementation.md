# MF-07 — Procedimento de implantação em produção

Passo a passo para levar a MF-07 à produção (Vercel + Neon). A história inclui a D-04, que
**muda o esquema de `servicos`**: os itens deixam a coluna `descricao` e passam para
`tipos_servico` + `servico_tipo_servico`. Código e banco precisam mudar juntos — ver
[Janela de indisponibilidade](#janela-de-indisponibilidade).

Spec: [MF-07](specs/MF-07.md) · Decisão: [D-04](specs/README.md#decisões-técnicas) ·
Plataforma: [MF-13](specs/MF-13.md)

## O que a migração faz

Três migrations, que rodam em sequência num único `migrate`:

| Ordem | Migration | Efeito |
|---|---|---|
| 1 | `2026_11_01_000045_create_tipos_servico_table` | Cria `tipos_servico` e `servico_tipo_servico`, vazias. |
| 2 | `2026_11_01_000047_backfill_tipos_servico_from_servicos_descricao` | Insere os 8 tipos do catálogo; percorre `servicos` em lotes de 500, lê o JSON de `descricao` e cria uma ligação por item. |
| 3 | `2026_11_01_000048_drop_descricao_from_servicos_table` | Remove a coluna `descricao`. |

Regras do preenchimento (passo 2):

- A comparação com o catálogo ignora maiúsculas e espaços nas pontas: `" motor "` e
  `"MOTOR"` viram `Motor`. **Acento conta**: `Radio` e `Rádio` são tipos diferentes.
- Texto fora do catálogo vira **tipo inativo** — sai do formulário, mas continua no
  histórico e no relatório. Nenhum item se perde.
- Item repetido no mesmo serviço gera uma ligação só.

No PostgreSQL o Laravel roda cada migration dentro de uma transação: se o preenchimento
falhar no meio, aquele passo é desfeito inteiro, sem ligação pela metade.

## Pré-requisitos

- Acesso ao painel do Neon (para criar branch) e aos dois hosts do projeto: agrupado
  (`-pooler`) e direto.
- `.env` local com `DB_URL_UNPOOLED` — migração **sempre pelo host direto, nunca pelo
  pooler** (RF-13.4 da MF-13): o pooler em transaction mode não aceita `SET`, tabela
  temporária nem `PREPARE`.
- Sail no ar (`./vendor/bin/sail up -d`). Os comandos abaixo rodam no container como
  usuário `sail`.
- PR da `feature/MF-07` aprovado e mesclado em `develop`, e `develop` pronta para ir à `main`.

## Procedimento

### 1. Ensaio num branch do Neon

1. No painel do Neon, criar um branch a partir da produção (ex.: `mf-07-ensaio`). É uma
   cópia instantânea — e serve também de **backup** do estado anterior.
2. Apontar o `DB_URL_UNPOOLED` do `.env` local para o **host direto desse branch**.
3. Ver o que está pendente:

   ```bash
   docker exec -u sail marinaflow-laravel.test-1 php artisan migrate:status --database=pgsql-unpooled
   ```

   O esperado são só as três migrations da MF-07. Se aparecer outra pendente (de MEL03 ou
   MF-04, por exemplo), ela roda junto: conferir na história de origem se exige algum
   cuidado. Na base local, `2026_10_24_220000_change_servicos_status_default_to_aguardando_vistoria`
   ainda estava pendente quando a MF-07 foi feita.
4. Rodar a [consulta antes](#consulta-antes) no branch e guardar o resultado.
5. Rodar a migração:

   ```bash
   docker exec -u sail marinaflow-laravel.test-1 php artisan migrate --database=pgsql-unpooled
   ```

6. Rodar as [consultas depois](#consultas-depois) e comparar com o resultado do passo 4.
7. Opcional, mas recomendado: testar o caminho de volta no próprio branch
   (`migrate:rollback --step=3 --database=pgsql-unpooled`), conferir que `descricao`
   voltou preenchida e rodar `migrate` de novo.

Só seguir para a produção se o ensaio terminar sem erro e as contagens baterem.

### 2. Escolher o horário

Fora do expediente da marina. A janela é curta (ver abaixo), mas registro de serviço
feito durante ela falha.

### 3. Deploy e migração em produção

1. Mesclar `develop` em `main`. A Vercel faz o deploy sozinha a partir da `main`.
2. Enquanto o deploy roda, apontar o `DB_URL_UNPOOLED` de volta para o **host direto da
   produção**.
3. Conferir que `migrate:status --database=pgsql-unpooled` lista as mesmas pendentes do ensaio.
4. Assim que o deploy aparecer como `Ready` na Vercel, rodar:

   ```bash
   docker exec -u sail marinaflow-laravel.test-1 php artisan migrate --database=pgsql-unpooled
   ```

5. Rodar as [consultas depois](#consultas-depois) na produção.

### 4. Verificação funcional

1. Abrir **Histórico**: os serviços antigos mostram os mesmos itens de antes.
2. Abrir **Registrar Serviço**: o catálogo aparece com os 8 tipos; registrar um serviço de
   teste e conferir no Histórico (depois apagar ou concluir, conforme o costume da equipe).
3. Abrir **Relatórios** e gerar o PDF do **mês cheio mais movimentado**. Conferir no log da
   função na Vercel o tempo da requisição `GET /relatorios/servicos`.
   - Medição local com 500 serviços: ~6 s ([MF-07 › Medição](specs/MF-07.md#medição-tarefa-9)).
   - Acima de 10 s em produção: abrir tarefa. A saída barata é limitar o período a um
     mês; gerar em fila reabre a decisão de plataforma, porque `QUEUE_CONNECTION=sync`.
4. Apagar o branch de ensaio do Neon depois de alguns dias sem incidente.

## Consultas de conferência

### Consulta antes

Só leitura. Mostra quais textos existem hoje em `descricao` e quantas vezes cada um aparece.
Tudo o que não estiver entre os 8 do catálogo vai virar tipo inativo.

```sql
SELECT item, count(*) AS vezes
FROM servicos, json_array_elements_text(descricao) AS item
GROUP BY item
ORDER BY item;
```

Catálogo: `Buzina`, `Guincho da âncora`, `Lavagem`, `Limpeza`, `Luzes de navegação`,
`Motor`, `Rádio de som`, `radio VHF`.

### Consultas depois

```sql
-- Serviços sem nenhum item. Esperado: 0 (salvo serviço que já tinha descricao vazia).
SELECT count(*)
FROM servicos s
WHERE NOT EXISTS (SELECT 1 FROM servico_tipo_servico l WHERE l.servico_id = s.id);

-- Tipos criados a partir de texto fora do catálogo.
SELECT id, nome FROM tipos_servico WHERE ativo = false ORDER BY nome;

-- Contagem por tipo. Deve bater com a consulta antes, somando as variações
-- de caixa e espaço que foram unificadas.
SELECT t.nome, t.ativo, count(*) AS vezes
FROM servico_tipo_servico l
JOIN tipos_servico t ON t.id = l.tipo_servico_id
GROUP BY t.nome, t.ativo
ORDER BY t.nome;

-- A coluna antiga saiu. Esperado: nenhuma linha.
SELECT column_name FROM information_schema.columns
WHERE table_name = 'servicos' AND column_name = 'descricao';
```

## Janela de indisponibilidade

Código e esquema não mudam no mesmo instante:

| Situação | Efeito |
|---|---|
| Migração **antes** do deploy | O código antigo tenta gravar `descricao`: registro de serviço falha. |
| Deploy **antes** da migração | O código novo procura `tipos_servico`: Registrar Serviço, Histórico e Relatórios dão 500. |

O procedimento escolhe a segunda ordem e minimiza o intervalo: a migração roda assim que o
deploy termina, e leva segundos. Para o volume de uma marina, a janela fica abaixo de um
minuto.

Alternativa sem janela, **descartada**: manter `descricao` por uma versão e removê-la num
segundo deploy. Custa mais uma rodada manual de migração e um período com o mesmo dado em
dois lugares — não se paga com o número de usuários atual.

## Se der errado

Do mais leve ao mais pesado:

1. **Código:** na Vercel, *Instant Rollback* para o deploy anterior.
2. **Banco:** desfazer as três migrations (`--step=3` volta as três últimas; se outra
   pendente rodou junto, ela é mais antiga e fica de fora). Recria `descricao` a partir das ligações,
   inclusive de serviços registrados depois da migração (coberto por
   `tests/Feature/TipoServicoBackfillTest.php`):

   ```bash
   docker exec -u sail marinaflow-laravel.test-1 php artisan migrate:rollback --step=3 --database=pgsql-unpooled
   ```

   Fazer **junto** com o passo 1 — código antigo com esquema novo, ou o contrário, cai na
   mesma janela de indisponibilidade.
3. **Último recurso:** restaurar a produção a partir do branch de ensaio criado no passo 1
   do procedimento. Perde o que foi gravado depois da criação do branch.

Ao terminar, voltar o `DB_URL_UNPOOLED` do `.env` local para onde ele apontava antes, para
que nenhum comando de desenvolvimento rode contra a produção por engano.
