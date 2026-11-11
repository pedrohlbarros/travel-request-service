# 0036 — Detalhes da mensageria

- **Status:** Aceito
- **Data:** 2026-11-10
- **Refina:** [0007](0007-mensageria-rabbitmq-e-outbox.md), [0009](0009-infra-docker-e-perfis.md), [0034](0034-relacionamento-pedido-usuario.md)

## Contexto
A fase 04 tira os eventos de domínio do publisher nulo e leva até o RabbitMQ pelo outbox. O ADR 0007 fixa outbox, relay, topologia e formato. Ficam em aberto onde abrir a transação, quem monta a mensagem, de onde vêm os dados do evento gordo, o formato da tabela, o `traceparent` antes do OTel, os parâmetros do relay e como validar sem o consumidor da fase 05.

## Decisão
### Transação
- Port `Transaction` na Application (`Application\Shared\Port`), com `run(Closure)`. Os casos de uso que publicam eventos (aprovar e cancelar) rodam o `update` do repositório e o `publish` dentro dela. O adapter usa a transação da conexão do Laravel.
- O decorator de cache do repositório invalida **depois do commit** (`afterCommit` da conexão). Fora de transação, invalida na hora. Assim, uma leitura concorrente não recoloca no cache o estado anterior ao commit.

### Gravação no outbox
- `OutboxDomainEventPublisher` (`Infrastructure\Shared\Messaging`) implementa o `DomainEventPublisher` e grava uma linha por evento. Ele é genérico: cada context registra por tag um mapper que converte o seu evento de domínio em mensagem (exchange, routing key, subject e `data`). O Shared não conhece nenhum context.
- Evento sem mapper é erro de programação (`LogicException`) e desfaz a transação.
- O envelope CloudEvents é montado pelo publisher, igual para todos os contexts.

### Evento gordo
- O mapper do TravelRequest lê nome e e-mail do solicitante pelo `RequesterModel`, que passa a expor também o `email`. Segue o ADR 0034: leitura da tabela `users` só na Infrastructure, por model próprio e só de leitura. A leitura acontece dentro da transação da escrita.
- `data`, como no ADR 0007: `travel_request_id`, `requester` (`id`, `name`, `email`), `destination`, `departure_date`, `return_date` (`YYYY-MM-DD`), `status`, `version` e `approved_by` ou `cancelled_by` (só o id). Chaves em `snake_case`, como na API.

### Tabela `outbox_messages`
- `id` (UUIDv7 do evento, `BINARY(16)`), `exchange`, `routing_key`, `payload` (o CloudEvent inteiro em JSON, numa coluna `LONGTEXT`), `headers` (JSON), `occurred_at`, `published_at` (nula até publicar).
- O `payload` fica em texto porque uma coluna `JSON` do MySQL reescreve o documento (reordena as chaves), e o relay publica o que foi gravado byte a byte.
- Índice `(published_at, id)`: o relay busca as pendentes em ordem de id, que é a ordem de criação.
- O relay publica a linha como foi gravada.
- Não há limpeza das publicadas nesta fase. Evolução possível: um comando agendado que apaga as publicadas há mais de N dias.

### Envelope CloudEvents 1.0
- `id` = id da linha, `type` = routing key (`travel_request.approved.v1`), `source` = `/travel-request-service` (configurável por `.env`), `subject` = id do pedido, `time` = momento do evento em ISO 8601 UTC, `datacontenttype` = `application/json`, `specversion` = `1.0`.
- Na AMQP: modo estruturado, com `content_type` `application/cloudevents+json`, `message_id` = id do evento, `type` = routing key, `delivery_mode` persistente.

### `traceparent`
- O middleware de contexto guarda o `traceparent` válido da request no Context do Laravel como dado oculto (fora dos logs). O publisher copia esse valor para os headers da linha, e o relay manda os headers na mensagem.
- Request sem `traceparent` gera mensagem sem o header. A fase 06 troca a origem pelo span atual do OTel.

### Relay (`artisan outbox:relay`)
- Polling a cada 200 ms. Quando o outbox está vazio ou o broker falha, o intervalo dobra até 2 s, e volta a 200 ms quando algo é publicado.
- Lotes de 100 com `SELECT ... FOR UPDATE SKIP LOCKED` numa transação. Publica com publisher confirms e só então marca `published_at` e faz commit.
- Os três valores são configuráveis por `.env`.
- Declara o exchange de forma idempotente (topic, durável) antes de publicar.
- Falha do broker: o lote volta (rollback), o relay loga um warning, descarta a conexão e tenta de novo no próximo ciclo. O evento fica pendente até sair.
- `SIGTERM`/`SIGINT` terminam o ciclo atual e encerram o processo. `--once` processa um lote e sai.

### Compose
Serviço `outbox-relay`, sem perfil, com a mesma imagem do app e `php artisan outbox:relay`. Depende de MySQL e RabbitMQ saudáveis, usa `restart: unless-stopped` e tem healthcheck pelo processo.

### Validação sem o consumidor
- `artisan messaging:tap` declara a fila `inspection.<exchange>` (quorum, limitada a 1000 mensagens) ligada a `#` e imprime as mensagens recebidas com routing key, propriedades, headers e payload.
- `artisan outbox:status` mostra pendentes, publicadas e a pendente mais antiga.
- Alvos `make events`, `make outbox` e `make relay-logs`.

### Testes
- O nome do exchange vem de config. A suíte usa `travel_request.events.test` e declara e apaga as próprias filas, sem misturar com o relay de dev.
- O teste de dois relays segura um lote numa segunda conexão MySQL. O de broker fora do ar aponta o relay para uma porta sem broker.

## Consequências
- A ordem de publicação é a de criação dentro de um relay. Com vários relays em paralelo, a ordem entre lotes não é garantida, e os consumidores usam `version` para descartar estados velhos.
- A leitura de `users` dentro da transação da escrita acrescenta uma query a aprovar e a cancelar.
- O `outbox_messages` cresce sem limite até existir a limpeza.
