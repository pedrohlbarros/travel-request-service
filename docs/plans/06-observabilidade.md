# Fase 06 — Observabilidade

- **Status:** Aprovado para início
- **ADRs:** 0009, 0010, 0022, 0023, 0036, 0038

## Objetivo
Trace ponta a ponta e métricas da aplicação no perfil `obs`.

## Escopo
- Perfil `obs`: OTel Collector (porta única de entrada), Jaeger, Prometheus, Loki, Grafana (portas via `.env`) (ADR 0038).
- Instrumentação OTel: extensão + SDK + auto Laravel (HTTP, MySQL, cache/Redis) e spans manuais no relay (publish), consumidor (consume) e envio das notificações; um trace pai-filho de ponta a ponta.
- Métricas pelo OTel (sem `/metrics` na app): latência HTTP com p95/p99, criações e transições, outbox publicado/pendente, entregas por resultado, memória por processo; filas e DLQ pelo plugin Prometheus do RabbitMQ via Collector.
- Um dashboard provisionado no Grafana com API, pedidos, mensageria, runtime e logs; alerta de DLQ.
- Logs JSON com `request_id`, `trace_id` e `span_id`, também no Loki; `x-request-id` até o consumidor; 4xx como warning sem stack trace.
- Sampler que descarta os spans raiz de `/health/*`.
- Aplicação continua funcionando sem o perfil `obs`: `make obs` liga o SDK, `make obs-down` desliga.
- Makefile: `obs`, `obs-down`, `obs-urls`.

## Decidido na fase
- **Logs de erros esperados (levantado na fase 02):** exceções traduzidas para 4xx viram warning sem stack trace; o report padrão fica para os 5xx (ADR 0038).

## Critérios automáticos
- Testes de que `request_id`/`traceparent` são propagados (HTTP → outbox → mensagem).
- `make qa` verde com e sem `obs` no ar.

## Validação manual (usuário)
1. `make obs`, aprovar um pedido e abrir o trace no Jaeger: `POST .../approve` → SQL → publish (relay) → process (consumidor) → `notification.deliver` → `notification.broadcast` num único trace; nenhum trace de `/health`.
2. Abrir o dashboard no Grafana (`make obs-urls`): latência p95/p99, transições, outbox, filas, DLQ, entregas, memória e logs.
3. Errar a senha no login → warning sem stack trace no `make logs S=app` e no painel de logs (o caminho feliz não loga; os dados do `obs` recomeçam a cada `make obs`); seguir do log para o trace pelo `trace_id`.
4. `make obs-down` e confirmar que a API, o relay e o consumidor seguem funcionando.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
