# 0007 — Mensageria com RabbitMQ + Transactional Outbox

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
### Publicação
- Serviço **orientado a eventos**. Eventos de domínio no mínimo `approved` e `cancelled`.
- **Transactional Outbox:** o evento é gravado na tabela outbox na mesma transação do pedido.
- **Relay:** worker dedicado (`artisan outbox:relay`) em loop com polling (~200–500 ms, backoff quando vazio). Busca lotes com `SELECT ... FOR UPDATE SKIP LOCKED` (permite N relays em paralelo), publica com **publisher confirms** e só então marca `published_at`. Mensagens persistentes.
- Entrega **at-least-once**.

### Topologia
- **Um exchange topic por context publicador**, de propriedade dele: `travel_request.events` (durável).
- Routing keys `<context>.<evento>.v<n>`, ex. `travel_request.approved.v1`, `travel_request.cancelled.v1`.
- **Fila por consumidor**, de propriedade dele, com bind por padrão: `notification.travel_request` ← `travel_request.#`.
- Filas **quorum**; consumidor com ack manual e prefetch configurável.

### Formato
- **CloudEvents 1.0** em JSON: `id` (UUIDv7 = `event_id`), `source`, `type` (= routing key), `specversion`, `time`, `datacontenttype`, `subject` + `data`.
- **`traceparent`** (W3C) nos headers AMQP para o trace no Jaeger.
- **Evento gordo** (event-carried state transfer): `data` leva `travel_request_id`, `requester` (`id`, `name`, `email`), `destination`, datas de ida/volta, `status`, `version`, `approved_by`/`cancelled_by`. O Notification não consulta outros contexts.

### Consumo
- **Idempotência por inbox table** no MySQL: `processed_events (consumer, event_id)` com PK única, inserida na mesma transação do efeito local. Duplicado → ack sem reprocessar.
- **Retry com backoff + DLQ:** falha transitória vai para filas de espera com TTL + dead-letter de volta (ex.: 10 s, 1 min, 5 min), tentativas no header. Esgotou ou erro permanente (payload inválido) → `notification.travel_request.dlq`. Métrica/alerta de DLQ no Grafana.

## Consequências
- Separar os contexts em serviços não muda a topologia.
- O payload carrega PII (nome/e-mail do solicitante) no broker — registrar na entrega.
- Relay e consumidores sobem sem perfil (ADR 0009).
