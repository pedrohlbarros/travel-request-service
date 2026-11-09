# 0010 — Observabilidade

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- Stack completa no perfil `obs`: **OpenTelemetry** (instrumentação + Collector), **Jaeger** (traces), **Prometheus** (métricas), **Grafana** (dashboards).
- Objetivo: trace ponta a ponta de um fluxo (HTTP → banco → outbox → RabbitMQ → Notification).
- **Opcional, se der tempo:** um frontend simples para o trace começar no navegador e aparecer inteiro no Jaeger.

## Consequências
- A aplicação precisa funcionar sem o perfil `obs` no ar.
- Verificar compatibilidade da extensão OTel com Swoole/corrotinas (ADR 0001).
