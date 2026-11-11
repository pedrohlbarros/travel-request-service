# 0038 — Detalhes da observabilidade

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0009](0009-infra-docker-e-perfis.md), [0010](0010-observabilidade.md), [0022](0022-imagem-base-e-versoes.md), [0023](0023-dependencias-e-drivers-da-fundacao.md), [0036](0036-detalhes-da-mensageria.md)

## Contexto
A fase 06 liga o OpenTelemetry que a fase 00 deixou desligado. O ADR 0010 fixa a stack (Collector, Jaeger, Prometheus e Grafana) e o objetivo de um trace ponta a ponta. Ficam em aberto:
- como instrumentar e como passar o trace pela mensageria;
- de onde vêm as métricas num Octane com vários workers;
- o que fazer com os logs;
- como ligar e desligar o SDK sem travar a app quando o `obs` está fora do ar;
- como testar tudo isso sem o Collector.

## Decisão
### OpenTelemetry como porta única
- Traces, métricas e logs saem pelo SDK OTel por OTLP http/protobuf para o Collector, que é o único ponto de entrada. Não há `/metrics` na app.
- O Collector exporta traces para o Jaeger, métricas para o exporter Prometheus (porta interna 8889, raspada pelo Prometheus) e logs para o Loki, pelo endpoint OTLP dele.
- O Loki entra no perfil `obs` como serviço novo, e o Grafana consulta as três fontes.

### Instrumentação
- Imagem: extensão `opentelemetry` 1.5.0 via PECL.
- Pacotes: `open-telemetry/sdk`, `exporter-otlp`, `opentelemetry-auto-laravel` e `google/protobuf`. O plugin `tbachert/spi` foi liberado em `allow-plugins`, porque é por ele que a auto-instrumentação se registra.
- O SDK sobe pelo autoload (`OTEL_PHP_AUTOLOAD_ENABLED=true`), configurado só por variáveis `OTEL_*`. Essas variáveis entram no ambiente dos containers pelo compose (âncora `x-otel-environment`), porque o SDK as lê antes de o Laravel carregar o `.env`.
- Os providers são criados no primeiro uso, já dentro de cada processo, e o detector `service_instance` dá a cada worker, ao relay e ao consumidor um `service.instance.id` próprio. Cada processo também tem o próprio `service.name`: `travel-request-service`, `travel-request-outbox-relay` e `travel-request-notification`.
- O auto Laravel cobre HTTP, queries, cache e Redis. Spans manuais na Infrastructure cobrem publish no relay, consume no consumidor, `notification.deliver` (database + mail) e `notification.broadcast`. Domain e Application não sabem de trace.
- `Infrastructure\Shared\Observability\Telemetry` é a única entrada do nosso código: tracer, meter, `inSpan`, propagação e flush.

### Propagação pela mensageria
- Cadeia pai-filho em um único trace:
  - o outbox grava o `traceparent` do span atual (o span HTTP);
  - o relay abre um span de publish filho dele por mensagem e injeta o próprio contexto na mensagem;
  - o consumidor extrai esse contexto e abre o span de consume, e dentro dele ficam DB, mail e broadcast.
- Com o SDK desligado, o outbox continua gravando o `traceparent` recebido na request, como no ADR 0036.
- O `request_id` vai no header `x-request-id` até o consumidor, que o coloca no Context do Laravel, e daí nos logs, durante a mensagem.

### Métricas
- Cada processo exporta cumulativo com o próprio `service.instance.id`; o Prometheus recebe `instance` por processo, e os painéis somam.
- Métricas:
  - HTTP: `http.server.request.duration` por rota (o template, não o path), método e status, com buckets para p95 e p99;
  - negócio: `travel_request.created` e `travel_request.transitions{status}`, contadas depois do commit;
  - mensageria: `outbox.published` e `outbox.pending` (este só no relay, para ter uma série só), mais `notification.deliveries{outcome}` (delivered, duplicate, retried, dead_lettered) e `notification.delivery.duration`;
  - runtime: `process.memory.usage` e `process.memory.peak` por processo.
- As filas e a DLQ vêm do plugin Prometheus do RabbitMQ (`/metrics/detailed?family=queue_coarse_metrics`), raspado pelo receiver `prometheus` do Collector.

### Export em processo longo
- Os spans e os logs saem no fim de cada unidade de trabalho: o `RequestTerminated` do Octane (depois da resposta), cada ciclo do relay e cada ciclo do consumidor.
- As métricas saem no mesmo ponto, no máximo uma vez por `OTEL_METRIC_EXPORT_INTERVAL` (10 s).
- A amostragem é parent based, sempre ligada, exceto nos spans raiz de `/health/*`: o healthcheck do Docker os chama a cada 5 s. Um sampler próprio (`HealthCheckSampler`) descarta esses spans. Como o SDK configurado por env não aceita sampler próprio, um initializer dos Globals (`bootstrap/telemetry.php`, carregado pelo autoload do composer) troca só o tracer provider. A métrica de latência continua contando o health.

### Ligar e desligar
- `OTEL_SDK_DISABLED` vem do ambiente do compose, com `true` como padrão. `make obs` sobe o perfil e recria app, relay e consumidor com o SDK ligado. `make obs-down` derruba o perfil e recria com o SDK desligado.
- Sem o `obs`, não há custo de export.

### Logs
- O canal `stderr` passa a JSON (Monolog `JsonFormatter`), com `trace_id` e `span_id` do span atual (`TraceContextLogProcessor`).
- O `LogWatcher` do auto Laravel manda os mesmos logs por OTLP para o Loki.
- Exceção traduzida para 4xx vira warning, sem stack trace, com `status`, `type`, classe e motivo (`ClientErrorReporter`). O report padrão do Laravel fica só para os 5xx.

### Grafana
- Datasources Prometheus, Jaeger e Loki provisionados.
- Um dashboard único com:
  - API: RPS, p50/p95/p99, p95/p99 por rota e 4xx/5xx;
  - pedidos: criações, aprovações e cancelamentos;
  - mensageria: outbox pendente, publicadas, filas, DLQ, entregas por resultado e p95/p99 da entrega;
  - runtime: memória e requests por processo;
  - logs do Loki.
- Alerta provisionado: DLQ > 0 por 1 min. Login anônimo só de leitura.

### Imagens e portas
- `otel/opentelemetry-collector-contrib:0.165.0`, `jaegertracing/jaeger:2.23.0`, `prom/prometheus:v3.16.0`, `grafana/loki:3.6.20` e `grafana/grafana:13.2.6`.
- Portas no host por `.env`: `FORWARD_GRAFANA_PORT` 3000, `FORWARD_JAEGER_UI_PORT` 16686, `FORWARD_PROMETHEUS_PORT` 9090 e `FORWARD_LOKI_PORT` 3100. O Collector não publica porta.

### Testes
- Os testes trocam os providers globais por providers em memória (trait `CapturesTelemetry`) e verificam spans, parentesco, headers e métricas sem o Collector.
- A auto-instrumentação guarda o primeiro tracer que recebe no processo. Por isso a suíte registra no bootstrap um tracer provider comutável, que cada teste aponta para o provider em memória.

## Consequências
- O `make qa` passa com e sem o `obs` no ar: a suíte força o SDK desligado e liga só os providers em memória.
- Um `make up` com o `obs` no ar desliga o SDK de novo, então o perfil volta a ser ligado com `make obs`.
- As séries de um worker reiniciado expiram no exporter do Collector em 5 min.
- O initializer constrói o tracer provider de novo depois do SDK, então o provider criado pelas variáveis fica sem uso.
- O log só tem o que pede atenção: 4xx, retry, DLQ e broadcast que falhou. O caminho feliz aparece nos traces e nas métricas, não no log.
- Os dados do perfil `obs` são efêmeros. Loki, Prometheus e Jaeger não têm volume, e cada `make obs` depois de um `make obs-down` começa do zero.
