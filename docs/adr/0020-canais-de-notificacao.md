# 0020 — Canais de notificação

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
O context Notification, ao consumir `travel_request.approved.v1` / `cancelled.v1`, notifica o solicitante via `notify` do Laravel nos canais:
1. **Mail** — SMTP apontando para o Mailpit no Docker.
2. **Database** — notificações in-app, com `GET /api/v1/notifications` e `POST /api/v1/notifications/{id}/read`.
3. **Broadcast** — WebSocket via **Reverb**, em tempo real (casa com o frontend opcional).

## Consequências
- Reverb é mais um processo sem perfil no compose (ADR 0009).
- Idempotência pela inbox table (ADR 0007).
