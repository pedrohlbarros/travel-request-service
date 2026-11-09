# Fase 04 — Mensageria (Outbox + RabbitMQ)

- **Status:** Proposto
- **ADRs:** 0007, 0009, 0017

## Objetivo
Eventos de domínio saindo de forma confiável para o RabbitMQ.

## Escopo
- Tabela outbox gravada na mesma transação do pedido.
- `artisan outbox:relay`: polling com backoff, lotes com `FOR UPDATE SKIP LOCKED`, publisher confirms, `published_at`.
- Topologia: exchange topic `travel_request.events`, routing keys `travel_request.<evento>.v1`; declaração idempotente.
- Mensagem CloudEvents 1.0 (evento gordo) com `traceparent` nos headers.
- Serviço do relay no compose (sem perfil).
- Makefile: alvos para ver estado do outbox/filas, se úteis para validação.

## Critérios automáticos
- Integration: evento persiste junto com o pedido (rollback não deixa evento); relay publica e marca; dois relays em paralelo não duplicam; broker fora do ar → evento fica pendente e sai quando volta.
- Contrato do CloudEvent validado em teste.

## Validação manual (usuário)
1. Aprovar um pedido e ver a mensagem no management do RabbitMQ (fila de teste/inspeção).
2. Parar o RabbitMQ, aprovar outro pedido, subir de novo → mensagem chega.
3. Conferir payload e headers da mensagem.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
