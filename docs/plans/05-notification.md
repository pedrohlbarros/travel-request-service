# Fase 05 — Notification

- **Status:** Proposto
- **ADRs:** 0007, 0009, 0016, 0020

## Objetivo
O solicitante é notificado por mail, database e broadcast sempre que um pedido é aprovado ou cancelado.

## Escopo
- Consumidor da fila quorum `notification.travel_request` (bind `travel_request.#`), ack manual, prefetch configurável.
- Inbox table `processed_events` para idempotência.
- Retry com backoff (filas de espera com TTL) e DLQ `notification.travel_request.dlq`.
- Notifications do Laravel com canais mail (Mailpit), database e broadcast (Reverb).
- Endpoints `GET /api/v1/notifications` e `POST /api/v1/notifications/{id}/read`.
- Reverb e consumidor no compose (sem perfil).
- Makefile: alvos para reprocessar/inspecionar DLQ, se úteis.

## Critérios automáticos
- Integration: mensagem duplicada não gera notificação duplicada; falha transitória é reentregue com backoff; payload inválido vai para DLQ.
- E2E: aprovar pedido → e-mail no Mailpit (API do Mailpit) + notificação database.
- Feature: endpoints de notificações (escopo por usuário).

## Validação manual (usuário)
1. Aprovar e cancelar pedidos e ver os e-mails no Mailpit.
2. Listar notificações via API e marcar como lida.
3. Ver o broadcast chegando (ex.: cliente WebSocket simples ou log do Reverb).
4. Parar o Mailpit, aprovar um pedido, ver retry na fila; subir o Mailpit → e-mail chega.
5. Publicar uma mensagem inválida manualmente → cai na DLQ.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
