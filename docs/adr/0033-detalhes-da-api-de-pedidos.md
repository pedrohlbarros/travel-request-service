# 0033 — Detalhes da API de pedidos

- **Status:** Aceito (Resource sem nome do solicitante e `requester_id` sem foreign key substituídos por [0034](0034-relacionamento-pedido-usuario.md))
- **Data:** 2026-11-10
- **Refina:** [0004](0004-autorizacao-role-e-policies.md), [0006](0006-concorrencia-versionamento-otimista.md), [0008](0008-papeis-do-redis.md), [0016](0016-convencoes-da-api.md), [0028](0028-modelagem-do-dominio-travel-request.md)

## Contexto
A fase 03 expõe o context TravelRequest em REST. Os ADRs anteriores fixam rotas, filtros, códigos de conflito e papéis do Redis, mas deixam em aberto a semântica do PATCH, onde ficam o cache e as Policies, o formato do ETag e a exposição da doc.

## Decisão
### PATCH parcial
- O corpo traz só os campos que mudam (`destination`, `departure_date`, `return_date`), com pelo menos um.
- O domínio recebe os campos opcionais, monta o período com o valor atual do que faltar, valida e altera só o que mudou de fato.
- O UPDATE grava só as colunas alteradas, mais `version` e `updated_at`.
- PATCH sem mudança real (mesmos valores) é no-op: não grava nem incrementa a versão e responde 200 com o estado e o `ETag` atuais. As checagens de dono, estado e `If-Match` valem do mesmo jeito.
- A regra "ida ≥ hoje" só é checada quando as datas mudam, para que um pedido com ida já passada ainda possa corrigir só o destino.
- Não há evento nem tabela de auditoria da edição nesta fase. Como o domínio já sabe quais campos mudaram, um `TravelRequestDetailsChanged` fica como evolução possível.

### Ordem das checagens nas escritas
404 (inexistente ou alheio) → 412 (versão) → 409 (estado). O dono é checado antes da versão, para que um `If-Match` errado não revele que um pedido alheio existe. Nas ações de admin, o 403 da Policy vem antes de qualquer leitura.

### ETag e If-Match
- O `ETag` é strong, no formato `"<version>"`. Ele sai no GET do detalhe, no 201 do create (junto com `Location`) e no 200 do PATCH, do `/approve` e do `/cancel`.
- `If-Match` aceita uma única entity-tag strong. Valor weak (`W/"…"`), `*`, lista ou formato inválido não casam e dão 412. A falta do header dá 428.
- `If-None-Match`/304 fica fora por ora.

### Policies
`TravelRequestPolicy` decide só pelo papel: `approve`, `cancel` e `viewAll`. O controller passa o resultado de `viewAll` como `isViewerAdmin` para Get e List. O dono continua no domínio (edição) e no caso de uso (leitura), sem carregar o pedido duas vezes.

### Leitura e cache
- A leitura (Get e List) passa por uma port de query service na Application (`TravelRequestQueries`), com adapter de query builder na Infrastructure.
- O cache é um decorator do query service na Infrastructure. O detalhe é cacheado por ID. A lista usa a chave hash(escopo + filtros + cursor + per_page) e a geração do escopo.
- A invalidação é um decorator do repositório na Infrastructure: no `add`/`update` ele apaga o detalhe e incrementa as gerações `owner:{requester_id}` e `admin`. A Application não sabe que existe cache.
- Os TTLs são configuráveis por `.env` (`TRAVEL_REQUEST_CACHE_DETAIL_TTL` 300 s, `TRAVEL_REQUEST_CACHE_LIST_TTL` 30 s). O "não encontrado" não é cacheado.
- O cache guarda os Outputs serializados. O `cache.serializable_classes` libera só essas classes (`TravelRequestOutput`, `TravelRequestPage`, o enum de status e `DateTimeImmutable`), mantendo a proteção contra gadget chain.

### Listagem
- A ordem é `id` desc: o UUIDv7 é ordenado no tempo, então a ordem é do mais novo para o mais antigo, com keyset sem coluna extra.
- O cursor é opaco: base64url de `{id da borda, direção}`, com implementação própria. O `cursorPaginate` do Laravel não serve aqui, porque guarda o valor do `id` já convertido pelo cast (string) e compara com a coluna `BINARY(16)`.
- Cursor inválido ou `per_page` fora de 1–100 dá 422.
- Resposta: `data`, `meta` (`per_page`, `next_cursor`, `prev_cursor`) e `links` (`next`, `prev`).
- O filtro `destination` faz LIKE com `%` e `_` escapados, case-insensitive pela collation. As datas de `created_*` são inclusivas, em UTC.

### Erros
- ID que não é UUIDv7 (no path) dá 404 `/problems/not-found`, como um ID inexistente. A rota já barra o que não é UUID.
- Formato da entrada vem do FormRequest (422 com `errors`). Regra de data que depende do valor armazenado (ex.: PATCH só da volta, antes da ida gravada) vem do domínio, como 422 `/problems/validation-failed` sem `errors`, com a causa em `detail`.

### Persistência
- `requester_id` sem foreign key para `users`: os contexts só se falam por eventos (ADR 0024).
- Índices: `(requester_id, id)`, `(status, id)`, `created_at`, `departure_date` e `return_date`.

### Resource
O Resource tem `id`, `requester_id`, `destination`, `departure_date`, `return_date`, `status`, `version`, `decided_by`, `created_at` e `updated_at`. Nome e e-mail do solicitante não entram: o context TravelRequest não lê o Identity (ADR 0024).

### Doc da API
- O Scramble (`dedoc/scramble` 0.13) só fica acessível com `APP_ENV=local`, que é o padrão dele (gate `viewApiDocs`). Fica em `/docs/api`, e o `make docs` exporta o JSON para `storage/openapi.json` (fora do git).
- Duas extensões completam o que o Scramble não infere. Uma documenta cada erro como Problem Details, lido do mesmo registro de types que o renderer usa. A outra documenta `If-Match`, o 428 e o header `ETag`.
- O bearer só aparece nas rotas com middleware `auth`.

### Eventos
O `DomainEventPublisher` ganha um adapter nulo até o outbox da fase 04.

## Consequências
- O domínio passa a ter `changeDetails` com campos opcionais, e o caso de uso deixa de exigir os três campos.
- A invalidação depende de toda escrita passar pelo repositório. Escrita direta no banco deixa o cache velho até o TTL.
- O admin não vê o nome do solicitante na lista. Mostrar o nome exigiria uma projeção alimentada por eventos do Identity.
