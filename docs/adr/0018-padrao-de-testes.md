# 0018 — Padrão de testes

- **Status:** Aceito (metas de MSI e cobertura substituídas por [0035](0035-cobertura-e-msi-em-100.md))
- **Data:** 2026-11-09

## Decisão
### Estrutura: `tests/` espelha `src/`
```
tests/
  Domain/<Context>/          # suíte unit
  Application/<Context>/     # suíte unit (fakes das ports)
  Infrastructure/<Context>/  # suíte integration (MySQL, Redis, RabbitMQ, outbox reais)
  Interfaces/Http/<Context>/ # suíte feature (HTTP pela API)
  EndToEnd/                  # fluxos entre contexts/processos (ex: aprovar → outbox → RabbitMQ → e-mail no Mailpit)
  Support/                   # fakes in-memory, builders, traits
```
Suítes PHPUnit por diretório: `unit`, `integration`, `feature`, `e2e`.

### Convenções
- **Nomes:** `test_` + PascalCase, ex. `test_AdminApprovesRequestedTravelRequest`, `test_CannotCancelApprovedTravelRequest`.
- **Banco:** **MySQL real no Docker**, em database de teste separada. Sem SQLite.
- **Dublês:** **fakes in-memory das ports** em `tests/Support` (ex: `InMemoryTravelRequestRepository`, `FrozenClock`, `SpyEventPublisher`). Testes verificam estado, não chamadas; mocks só quando não houver alternativa.

### Metas (quebram o build)
- **Infection** sobre `src/Domain` e `src/Application` (suíte unit): **MSI ≥ 90%** e **Covered MSI ≥ 95%**.
- **Cobertura de linhas global ≥ 85%**, sem meta específica para `Infrastructure`.

### Carga (k6, perfil `load`)
- **Smoke:** 1 VU, sanidade dos fluxos.
- **Load:** carga esperada (ex.: 50 VUs por 5 min). Thresholds que falham o teste: **p95 < 200 ms** e **erros < 1%**.
- **Stress:** rampa até quebrar, sem threshold — objetivo é achar o teto.
- **Concorrência:** N admins aprovando/cancelando o mesmo pedido ao mesmo tempo; critério: **exatamente 1 sucesso**, o resto 412/409.
