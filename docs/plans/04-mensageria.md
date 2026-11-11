# Fase 04 — Mensageria (Outbox + RabbitMQ)

- **Status:** Aprovado para início
- **ADRs:** 0007, 0009, 0017, 0034, 0036

## Objetivo
Eventos de domínio saindo de forma confiável para o RabbitMQ.

## Escopo
- Port `Transaction` na Application; aprovar e cancelar gravam pedido e evento na mesma transação; cache invalidado após o commit (ADR 0036).
- Tabela `outbox_messages` gravada por um publisher genérico com mapper por context; evento gordo com nome e e-mail do solicitante lidos pelo `RequesterModel`.
- `artisan outbox:relay`: polling 200 ms → 2 s, lotes de 100 com `FOR UPDATE SKIP LOCKED`, publisher confirms, `published_at`, reconexão, `SIGTERM` limpo, `--once`.
- Topologia: exchange topic `travel_request.events`, routing keys `travel_request.<evento>.v1`; declaração idempotente.
- Mensagem CloudEvents 1.0 (evento gordo) com `traceparent` recebido na request nos headers.
- Serviço `outbox-relay` no compose (sem perfil).
- Validação: `artisan messaging:tap` (fila de inspeção) e `artisan outbox:status`; alvos `make events`, `make outbox`, `make relay-logs`.

## Critérios automáticos
- Integration: evento persiste junto com o pedido (rollback não deixa evento); relay publica e marca; dois relays em paralelo não duplicam; broker fora do ar → evento fica pendente e sai quando volta.
- Contrato do CloudEvent validado em teste.

## Validação manual (usuário)
1. `make events` uma vez (declara a fila de inspeção). Aprovar um pedido (com `traceparent` na request) e rodar `make events` de novo: a mensagem aparece, e também na fila `inspection.travel_request.events` do management.
2. `docker compose stop rabbitmq`, aprovar outro pedido, `make outbox` (1 pendente), `docker compose start rabbitmq`, `make outbox` (0 pendentes) e `make events` → mensagem chega.
3. Conferir payload (CloudEvent, `data` gordo) e headers (`traceparent`) da mensagem; `make relay-logs` mostra o warning do período sem broker.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
