# 0037 — Detalhes do Notification

- **Status:** Aceito
- **Data:** 2026-11-10
- **Refina:** [0007](0007-mensageria-rabbitmq-e-outbox.md), [0016](0016-convencoes-da-api.md), [0017](0017-identificadores-uuidv7.md), [0020](0020-canais-de-notificacao.md), [0036](0036-detalhes-da-mensageria.md)

## Contexto
A fase 05 consome os eventos de decisão do TravelRequest e avisa o solicitante por mail, database e broadcast. Os ADRs 0007 e 0020 fixam fila, inbox, retry com DLQ e os três canais. Ficam em aberto como a entrega roda, quem é o destinatário, o formato da tabela, os parâmetros do retry, as camadas do context, o idioma, o canal do broadcast, a API de leitura e as ferramentas de validação.

## Decisão
### Entrega
- Síncrona no consumidor. Numa transação: entrada no inbox (`processed_events`), notificação na tabela `notifications` e envio do e-mail. Se o SMTP falha, tudo volta e a mensagem vai para o retry do RabbitMQ.
- O broadcast sai depois do commit e é best effort: uma falha vira warning no log e a mensagem é confirmada mesmo assim. A notificação continua disponível na API, e não há retry, para não duplicar o e-mail.
- A entrega é at-least-once. Se o commit falhar depois de o e-mail ter saído, o retry pode reenviar o e-mail.

### Destinatário
- O Laravel notifica um `NotifiableRecipient` montado em memória com id, nome e e-mail que vêm do próprio evento gordo. O Notification não consulta `users`, como o ADR 0007 pede.

### Tabela `notifications`
- `id` em `BINARY(16)` (o próprio id do evento, então um evento gera no máximo uma notificação), `notifiable_id` em `BINARY(16)`, `type`, `message`, `data` (JSON em `TEXT`), `read_at` e `created_at`. Não há `notifiable_type`, porque só existe um tipo de destinatário.
- Índices `(notifiable_id, id)` e `(notifiable_id, read_at, id)`.
- `data` fica em texto pelo mesmo motivo do `payload` do outbox (ADR 0036): uma coluna `JSON` do MySQL reordena as chaves.
- Um canal próprio (`NotificationsTableChannel`) grava nela no lugar do `DatabaseChannel` do Laravel, que usa as colunas morph.

### Fila, retry e DLQ
- Fila quorum `notification.travel_request` com bind `travel_request.#` no exchange de eventos. O consumidor declara a fila, o bind, as filas de espera e a DLQ na subida.
- Filas de espera quorum `notification.travel_request.retry.10s`, `.retry.1m` e `.retry.5m`, com TTL e dead-letter de volta para a fila principal pelo exchange padrão. A tentativa vai no header `x-retry-count`.
- A 4ª falha vai para `notification.travel_request.dlq` com `x-death-reason` = `retries_exhausted`. Uma mensagem ilegível (JSON, tipo, campos, id ou e-mail inválidos) vai direto, com `x-death-reason` = `unreadable`. O erro vai em `x-last-error`.
- A mensagem só recebe ack depois de resolvida: entregue, publicada na fila de espera ou na DLQ, com publisher confirms.
- Prefetch 10. Fila, delays (`NOTIFICATION_RETRY_DELAYS_MS`) e prefetch são configuráveis por `.env`. A suíte usa a fila `notification.travel_request.test`.

### Camadas
- `Domain\Notification`: VOs `Recipient` e `TravelRequestDecision` (validam ids, nome, e-mail, destino, resultado e datas) e o enum `DecisionOutcome`.
- `Application\Notification`:
  - `NotifyRequesterOfDecisionUseCase`;
  - ports `ProcessedEvents`, `DecisionNotifier` (canais duráveis), `DecisionBroadcaster` (tempo real) e `Notifications` (leitura e marcar como lida);
  - casos de uso de listar e de marcar como lida.
- `Infrastructure\Notification`: o parser do CloudEvent, que lê só a mensagem, sem código do TravelRequest; o consumidor AMQP; as Notifications do Laravel; o canal da tabela; o adapter do Reverb.

### Idioma
E-mail e `message` da notificação em pt-BR, com arquivos de tradução em `lang/pt_BR` (`NOTIFICATION_LOCALE`). As chaves do JSON e os erros da API seguem em inglês. As datas no texto usam `dd/mm/aaaa`.

### Broadcast
- Reverb (`laravel/reverb`, que traz o `pusher-php-server`), canal privado `users.{id}` e evento `travel_request.decided`. O payload tem `id`, `type`, `message`, `data` e `created_at`.
- A autorização do canal (`POST /broadcasting/auth`) usa o guard JWT e libera só o próprio usuário.

### API
- `GET /api/v1/notifications`: cursor keyset por `id`, do mais novo para o mais antigo, `per_page` de 1 a 100, `data`/`meta`/`links` como na lista de pedidos, e filtro opcional `unread=true`. Mostra só as do próprio usuário, inclusive para o admin.
- `POST /api/v1/notifications/{id}/read`: responde 200 com a notificação. É idempotente e mantém o primeiro `read_at`. Notificação alheia ou inexistente dá 404. Não usa `If-Match`.
- O cursor (`ListCursor`) e `InvalidListCursor` passam para o Shared, usados pelas duas listas.

### Processos e validação
- Serviços `notification-consumer` (`artisan notification:consume`, que para limpo com SIGTERM) e `reverb` (`artisan reverb:start --debug`, porta `FORWARD_REVERB_PORT`) no compose, sem perfil.
- `artisan notification:dlq` lista a DLQ sem remover nada, e `--replay` devolve as mensagens para a fila principal com `x-retry-count` zerado.
- A página `/docs/notifications`, só com `APP_ENV=local`, assina o canal com o token colado e mostra o broadcast ao vivo, com Echo e pusher-js por CDN.
- Alvos `make dlq`, `make dlq-replay`, `make consumer-logs`, `make reverb-logs` e `make mailpit`.
- O teste E2E chama a API do Octane em execução e espera o relay e o consumidor do compose. Confere o e-mail pela API do Mailpit e a notificação pelo `GET /notifications`. Roda no banco de dev, com usuários criados por ele mesmo.

## Consequências
- A entrega depende do SMTP dentro da transação. Com o Mailpit fora, as decisões esperam nas filas de retry e, depois de cerca de 6 minutos, vão para a DLQ.
- Nome e e-mail ficam como estavam no momento da decisão. Uma troca de e-mail no Identity não muda as notificações antigas.
- A suíte de testes não depende do Reverb: o broadcast aponta para uma porta sem servidor, e a falha é tratada como no caso real.
