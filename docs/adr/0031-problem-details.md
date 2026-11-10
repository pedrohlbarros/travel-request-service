# 0031 — Critério e catálogo de Problem Details

- **Status:** Aceito
- **Data:** 2026-11-09
- **Refina:** [0006](0006-concorrencia-versionamento-otimista.md), [0016](0016-convencoes-da-api.md)

## Contexto
O ADR 0016 define erros em RFC 9457 com `type` relativo, e o 0006 lista alguns types, mas nenhum diz quando um erro merece `type` próprio.

## Decisão
### Critério
1. **Formato ou forma da entrada** (checável só pelo request: campo obrigatório, e-mail mal formado, data inválida): **um type genérico**, 422 `/problems/validation-failed`, com a extensão `errors` (`{campo: [mensagens]}`). Exceção de VO que chegue à API sai do mesmo jeito.
2. **Depende do estado armazenado ou de regra de negócio:** **type próprio**, porque o cliente reage de forma diferente a cada um (ex.: e-mail já cadastrado, transição inválida).
3. **Protocolo** (autenticação, precondição, rate limit): type próprio com o status HTTP correspondente.
4. Qualquer outro erro HTTP sem type próprio usa `about:blank` com o título padrão do status (RFC 9457). Erro inesperado é 500 `/problems/internal-error`, sem detalhe interno.

### Formato
`type`, `title`, `status`, `detail`, `instance` (path do request) + `request_id` e `trace_id` em todo erro.

### Mapeamento
Cada context registra no seu provider HTTP o mapa exceção → (status, type, title); `Interfaces\Http\Shared` só renderiza. O mapa é montado no boot, nunca por request (Octane).

### Catálogo
Mantido em [docs/api/problems.md](../api/problems.md), com significado e exemplo de cada type. Todo type novo entra no catálogo no mesmo PR.

## Consequências
- Um erro novo de formato não cria type; um erro novo de regra cria type e entrada no catálogo.
