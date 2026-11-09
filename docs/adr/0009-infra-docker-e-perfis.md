# 0009 — Docker Compose e perfis

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- A aplicação roda inteira via Docker Compose.
- **Sem perfil** (sobe sempre): tudo o que a aplicação precisa para funcionar — app (Octane/Swoole), MySQL, Redis, RabbitMQ, Mailpit, **Reverb** (canal broadcast) e os processos de apoio (workers de fila Redis, relay do outbox, consumidor do Notification).
- **Perfil `obs`:** OpenTelemetry Collector, Jaeger, Prometheus, Grafana (ADR 0010).
- **Perfil `load`:** k6, executado via `make load`; envia métricas ao Prometheus (remote write) quando `obs` está no ar, com dashboard k6 no Grafana.
- **Perfil `web`:** frontend opcional (build estático servido por Nginx, OTel JS exportando para o Collector). A API funciona sem ele.

### Portas
- **Toda porta publicada no host vem de variável de ambiente**, com o default de cada serviço, e está documentada no `.env.example`. Ex.: `FORWARD_DB_PORT=3306`, `FORWARD_REDIS_PORT=6379`, `FORWARD_RABBITMQ_PORT=5672`, `FORWARD_RABBITMQ_UI_PORT=15672`, `FORWARD_MAILPIT_UI_PORT=8025`, `FORWARD_GRAFANA_PORT=3000` (nomes finais na fase 0).
- Motivo: o avaliador provavelmente já tem MySQL ou outro serviço local na porta padrão; trocar a porta no `.env` antes de subir resolve sem editar o compose.
- Entre containers, sempre as portas internas padrão pelo nome do serviço.

