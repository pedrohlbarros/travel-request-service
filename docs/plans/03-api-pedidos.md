# Fase 03 — API de pedidos de viagem

- **Status:** Aprovado para início
- **ADRs:** 0003, 0004, 0006, 0008, 0015, 0016, 0017, 0028, 0033, 0034

## Objetivo
Expor o context TravelRequest via REST, com todos os recursos do Laravel exigidos pelo avaliador.

## Escopo
- Persistência Eloquent do agregado (`BINARY(16)`, coluna `version`, índices para os filtros), migrations.
- Relacionamento pedido–usuário: FKs para `users`, `RequesterModel` só leitura, resposta com o nome do solicitante (ADR 0034).
- Controllers em `Interfaces/Http`, FormRequests (validação), Resources, Policies (enum role), middlewares.
- Endpoints do ADR 0016: criar, listar, consultar, editar (PATCH parcial, no-op sem versão), `/approve`, `/cancel`.
- `ETag` strong `"<version>"` no GET e nas respostas de escrita, `If-Match` obrigatório nas escritas; 428 / 412 / 409 / 404 / 403 conforme ADR 0006, na ordem 403 → 404 → 412 → 409 (ADR 0033).
- `changeDetails` do domínio com campos opcionais; "ida ≥ hoje" só quando as datas mudam.
- Filtros planos (`status`, `destination`, `created_*`, `travel_*` com sobreposição) e paginação por cursor.
- Query service de leitura (port na Application); cache Redis por ID + listas por geração como decorators na Infrastructure, com invalidação num decorator do repositório.
- `TravelRequestPolicy` por papel (`approve`, `cancel`, `viewAll`).
- Problem Details para todos os erros, `type` em `/problems/...`.
- Scramble gerando o OpenAPI, acessível só com `APP_ENV=local`.
- Eventos de domínio ainda despachados para um publisher nulo (outbox real na fase 04).
- Makefile: alvo para abrir/listar a URL da doc e alvos de exemplo, se úteis à validação.

## Critérios automáticos
- Feature tests: cada endpoint, cada filtro, paginação, escopo por dono vs admin, cada código de erro, ETag/If-Match, invalidação de cache.
- Integration tests do repositório Eloquent e do query service (MySQL real).
- Teste de concorrência da atualização condicional (duas escritas com a mesma versão → uma 412).

## Validação manual (usuário)
1. `make docs` e abrir `/docs/api` (com `APP_ENV=local`): conferir rotas, schemas, `If-Match`/`ETag` e erros em Problem Details.
2. Como user: criar, listar, editar; tentar `/approve` → 403; tentar ver pedido de outro → 404.
3. Como admin: listar todos, aprovar com `If-Match` certo; repetir com versão antiga → 412; sem header → 428; cancelar aprovado → 409.
4. Conferir formato Problem Details de cada erro.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
