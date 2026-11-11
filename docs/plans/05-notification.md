# Fase 05 — Notification

- **Status:** Concluído (2026-11-10)
- **ADRs:** 0007, 0009, 0016, 0017, 0020, 0036, 0037

## Objetivo
O solicitante é notificado por mail, database e broadcast sempre que um pedido é aprovado ou cancelado.

## Escopo
- Consumidor da fila quorum `notification.travel_request` (bind `travel_request.#`), ack manual, prefetch configurável; declara a própria topologia (ADR 0037).
- Domínio fino (`Recipient`, `TravelRequestDecision`) e `NotifyRequesterOfDecisionUseCase`; destinatário montado do evento gordo, sem consultar `users`.
- Inbox table `processed_events` para idempotência, na mesma transação da notificação database e do e-mail; broadcast best effort depois do commit.
- Retry com filas de espera 10 s → 1 min → 5 min (TTL + dead-letter) e DLQ `notification.travel_request.dlq` com motivo.
- Notifications do Laravel com canais mail (Mailpit, pt-BR), tabela `notifications` própria em `BINARY(16)` e broadcast (Reverb, canal privado `users.{id}` com auth JWT).
- Endpoints `GET /api/v1/notifications` (cursor, `unread`) e `POST /api/v1/notifications/{id}/read` (idempotente).
- Reverb e consumidor no compose (sem perfil); página `/docs/notifications` (local) para ver o broadcast.
- Makefile: `dlq`, `dlq-replay`, `consumer-logs`, `reverb-logs`, `mailpit`.

## Critérios automáticos
- Integration: mensagem duplicada não gera notificação duplicada; falha transitória é reentregue com backoff; payload inválido vai para DLQ.
- E2E: aprovar pedido → e-mail no Mailpit (API do Mailpit) + notificação database.
- Feature: endpoints de notificações (escopo por usuário).

## Validação manual (usuário)
1. Aprovar e cancelar pedidos e ver os e-mails (pt-BR) no Mailpit (`make mailpit`).
2. Listar notificações via API (com e sem `unread=true`) e marcar como lida (repetir mantém o `read_at`).
3. Abrir `http://localhost:8000/docs/notifications`, colar o token do solicitante e ver o broadcast chegando ao aprovar.
4. Parar o Mailpit, aprovar um pedido, ver a mensagem em `notification.travel_request.retry.10s` (management); subir o Mailpit → e-mail chega no retry seguinte.
5. Publicar uma mensagem inválida no exchange (management) → `make dlq` mostra o motivo; `make dlq-replay` devolve à fila.

## Aprovação
- [x] Aprovado pelo usuário em: 2026-11-10
