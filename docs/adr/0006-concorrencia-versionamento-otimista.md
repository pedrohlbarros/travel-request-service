# 0006 — Concorrência e códigos de conflito

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
Corridas possíveis: dois admins aprovando e cancelando o mesmo pedido, dono editando enquanto um admin aprova.

## Decisão
### Versionamento otimista
- Coluna `version` no pedido; o update é `... WHERE id = ? AND version = ?` e incrementa a versão.
- `GET /travel-requests/{id}` devolve **`ETag`** com a versão. `version` também aparece no Resource.
- Escritas (`PATCH`, `/approve`, `/cancel`) exigem **`If-Match`**.

### Códigos HTTP
| Situação | Código | `type` (ADR 0016) |
|---|---|---|
| Escrita sem `If-Match` | **428 Precondition Required** | `/problems/precondition-required` |
| `If-Match` diferente da versão atual | **412 Precondition Failed** | `/problems/version-mismatch` |
| Versão ok, mas o estado não permite a ação (cancelar aprovado, aprovar cancelado, dono editar fora de `requested`) | **409 Conflict** | `/problems/invalid-status-transition`, `/problems/travel-request-not-editable` |
| Usuário comum acessando pedido de outro usuário | **404 Not Found** (não revela que o ID existe) | `/problems/not-found` |
| Usuário comum em `/approve` ou `/cancel` | **403 Forbidden** | `/problems/forbidden` |

## Consequências
- O 409 fica exclusivo para regra de estado; conflito de versão é sempre 412.
- Consultas de usuário comum são sempre escopadas por dono (daí o 404).
