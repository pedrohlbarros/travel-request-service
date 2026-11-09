# 0015 — Casos de uso: Command/Query + Handler

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- CQRS leve em `src/Application/<Context>`: Commands (`CreateTravelRequest`, `ApproveTravelRequest`...) e Queries (`ListTravelRequests`...) como `readonly class`, cada um com um Handler.
- Handlers chamados direto pelo controller via injeção de dependência, **sem bus**.
- O lado de leitura pode usar um read model/query service otimizado, separado do agregado.
