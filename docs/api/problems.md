# Catálogo de Problem Details

Todos os erros da API seguem a RFC 9457 (`application/problem+json`) com `type`, `title`, `status`, `detail`, `instance`, `request_id` e `trace_id`. Critério de quando um erro ganha `type` próprio: [ADR 0031](../adr/0031-problem-details.md).

| Status | `type` | Quando |
|---|---|---|
| 401 | `/problems/unauthenticated` | Access token ausente, mal formado, expirado, com assinatura, `iss` ou `aud` inválidos, ou revogado no logout |
| 401 | `/problems/invalid-credentials` | Login com e-mail ou senha errados (não diz qual) |
| 401 | `/problems/invalid-refresh-token` | Refresh token desconhecido, expirado, revogado ou reutilizado (o reuso revoga a família inteira) |
| 403 | `/problems/forbidden` | Autenticado, mas sem permissão para a ação |
| 404 | `/problems/not-found` | Recurso inexistente, de outro usuário ou com ID que não é UUIDv7 |
| 409 | `/problems/email-already-registered` | Cadastro com e-mail já existente |
| 409 | `/problems/invalid-status-transition` | Mudança de status não permitida (ex.: cancelar aprovado) |
| 409 | `/problems/travel-request-not-editable` | Edição de pedido fora de `requested` |
| 412 | `/problems/version-mismatch` | `If-Match` diferente do ETag atual (`"<version>"`), inclusive weak, `*` ou lista |
| 422 | `/problems/validation-failed` | Entrada inválida. Com `errors` quando vem da validação do request; sem `errors` (causa no `detail`) quando é regra do domínio contra o valor armazenado ou cursor de listagem inválido |
| 428 | `/problems/precondition-required` | Escrita sem `If-Match` |
| 429 | `/problems/too-many-requests` | Rate limit de login/refresh; header `Retry-After` |
| 500 | `/problems/internal-error` | Erro inesperado, sem detalhe interno |
| — | `about:blank` | Outros erros HTTP (ex.: 405), com o título padrão do status |

## Exemplo

```json
{
  "type": "/problems/validation-failed",
  "title": "The request is invalid.",
  "status": 422,
  "detail": "The email field must be a valid email address.",
  "instance": "/api/v1/auth/register",
  "errors": { "email": ["The email field must be a valid email address."] },
  "request_id": "019a6a10-aaaa-7bbb-8ccc-0123456789ab",
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736"
}
```
