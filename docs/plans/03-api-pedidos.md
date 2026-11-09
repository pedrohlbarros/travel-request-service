# Fase 03 — API de pedidos de viagem

- **Status:** Proposto
- **ADRs:** 0003, 0004, 0006, 0008, 0015, 0016, 0017

## Objetivo
Expor o context TravelRequest via REST, com todos os recursos do Laravel exigidos pelo avaliador.

## Escopo
- Persistência Eloquent do agregado (`BINARY(16)`, coluna `version`, índices para os filtros), migrations.
- Controllers em `Interfaces/Http`, FormRequests (validação), Resources, Policies (enum role), middlewares.
- Endpoints do ADR 0016: criar, listar, consultar, editar (PATCH), `/approve`, `/cancel`.
- `ETag` no GET, `If-Match` obrigatório nas escritas; 428 / 412 / 409 / 404 / 403 conforme ADR 0006.
- Filtros planos (`status`, `destination`, `created_*`, `travel_*` com sobreposição) e paginação por cursor.
- Query service de leitura; cache Redis por ID + listas por geração com invalidação nas escritas.
- Problem Details para todos os erros, `type` em `/problems/...`.
- Scramble gerando o OpenAPI.
- Eventos de domínio ainda despachados para um publisher em memória/nulo (outbox real na fase 04).

## Critérios automáticos
- Feature tests: cada endpoint, cada filtro, paginação, escopo por dono vs admin, cada código de erro, ETag/If-Match, invalidação de cache.
- Integration tests do repositório Eloquent e do query service (MySQL real).
- Teste de concorrência da atualização condicional (duas escritas com a mesma versão → uma 412).

## Validação manual (usuário)
1. Abrir a doc do Scramble e conferir rotas, schemas e erros.
2. Como user: criar, listar, editar; tentar `/approve` → 403; tentar ver pedido de outro → 404.
3. Como admin: listar todos, aprovar com `If-Match` certo; repetir com versão antiga → 412; sem header → 428; cancelar aprovado → 409.
4. Conferir formato Problem Details de cada erro.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
