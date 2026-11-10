# Fase 06 — Observabilidade

- **Status:** Proposto
- **ADRs:** 0009, 0010

## Objetivo
Trace ponta a ponta e métricas da aplicação no perfil `obs`.

## Escopo
- Perfil `obs`: OTel Collector, Jaeger, Prometheus, Grafana (portas via `.env`).
- Instrumentação OTel: HTTP, MySQL, Redis, relay do outbox, publish e consume no RabbitMQ (propagação via `traceparent`), envio das notificações.
- `/metrics` para o Prometheus (latência por rota, contadores de transição de status, tamanho do outbox pendente, DLQ).
- Dashboards provisionados no Grafana; alerta de DLQ.
- Logs JSON com `request_id` e `trace_id`, propagados para jobs e consumidores.
- Aplicação continua funcionando sem o perfil `obs`.
- Makefile: alvos para subir/derrubar `obs` e listar URLs.

## A decidir na fase
- **Logs de erros esperados (levantado na fase 02):** hoje o Laravel registra como `ERROR`, com stack trace, toda exceção que não é HTTP, inclusive as que o Problem Details traduz para 4xx (senha errada, token inválido, regra de domínio, validação). Sob carga isso polui o log e o alerta de erros. Recomendação: não reportar as exceções mapeadas para 4xx (`dontReport`/`ProblemType`) e manter no log só os 5xx, talvez com um log de nível `info` para auditoria de login.

## Critérios automáticos
- Testes de que `request_id`/`traceparent` são propagados (HTTP → outbox → mensagem).
- `make qa` verde com e sem `obs` no ar.

## Validação manual (usuário)
1. Subir `obs`, aprovar um pedido e abrir o trace no Jaeger: HTTP → DB → outbox → RabbitMQ → Notification → e-mail num único trace.
2. Abrir os dashboards no Grafana.
3. Derrubar `obs` e confirmar que a API segue funcionando.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
