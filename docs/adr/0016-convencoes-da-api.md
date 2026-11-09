# 0016 — Convenções da API REST

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
### Convenções gerais
- **Versionamento** no path: `/api/v1`. Nova versão só em breaking change.
- **Mudança de status como ação explícita** (sub-recurso). Sem `PATCH` genérico no campo `status`.
- **Listagem com paginação por cursor** (`cursor`, `per_page` padrão 20, máximo 100).
- **JSON em `snake_case`**; datas de viagem `YYYY-MM-DD`; timestamps ISO 8601 em UTC.
- **Erros RFC 9457 Problem Details** (`application/problem+json`): `type`, `title`, `status`, `detail`, `instance` + extensões (erros por campo, `trace_id`/`request_id`).
- **`type` como URL relativa documentada**, ex. `/problems/invalid-status-transition`. Cada type listado com significado e exemplo em `docs/` e no OpenAPI.
- **OpenAPI 3.1 gerado pelo Scramble** (`dedoc/scramble`).

### Rotas
```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
GET    /.well-known/jwks.json

POST   /api/v1/travel-requests
GET    /api/v1/travel-requests
GET    /api/v1/travel-requests/{id}
PATCH  /api/v1/travel-requests/{id}          (If-Match)
POST   /api/v1/travel-requests/{id}/approve  (If-Match, admin)
POST   /api/v1/travel-requests/{id}/cancel   (If-Match, admin)

GET    /api/v1/notifications
POST   /api/v1/notifications/{id}/read

GET    /health/live   /health/ready   /metrics
```

### Filtros de `GET /travel-requests` (planos)
| Param | Semântica |
|---|---|
| `status` | lista separada por vírgula (`requested,approved`) |
| `destination` | contém, case-insensitive |
| `created_from` / `created_to` | data de criação dentro da faixa |
| `travel_from` / `travel_to` | período da viagem **sobrepõe** a faixa |
| `cursor`, `per_page` | paginação |

Códigos de erro de concorrência, estado e acesso: ADR 0006.
