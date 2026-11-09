# 0017 — Identificadores UUIDv7

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- Pedidos, usuários, eventos (outbox/inbox/`event_id`) e refresh tokens usam **UUIDv7**, **gerados no domínio** (o agregado nasce com ID antes de persistir).
- Armazenamento **`BINARY(16)`** no MySQL; conversão string↔binário em cast/adapter de persistência. Na API, sempre string canônica.

## Consequências
- Índices e FKs compactos (PK clusterizada do InnoDB), ordenável por tempo (bom para cursor), não enumerável.
- Leitura direta no cliente SQL exige `BIN_TO_UUID()`.
