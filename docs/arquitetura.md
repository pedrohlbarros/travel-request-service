# Arquitetura

O Travel Request Service é um microsserviço Laravel organizado com **DDD** e **arquitetura hexagonal**. A API grava cada decisão na mesma transação que o evento que a anuncia, numa tabela de eventos pendentes de publicação (padrão **outbox transacional**). Um publicador lê essa tabela e envia os eventos ao **RabbitMQ**. Um consumidor entrega a notificação ao solicitante e mantém um registro dos eventos já processados (padrão **inbox**), para nunca entregar duas vezes. Cada decisão tem um ADR em [adr/](adr/README.md).

## Contextos e camadas

São três bounded contexts e um módulo transversal:

| Context | Responsabilidade |
|---|---|
| **Identity** | Cadastro, login, tokens JWT, refresh rotativo, revogação e papel do usuário |
| **TravelRequest** | Pedidos de viagem: criação, consulta, listagem, edição pelo dono e decisão pelo admin |
| **Notification** | Entrega das decisões ao solicitante (e-mail, banco, WebSocket) e a API de notificações |
| **Shared** | O que é transversal: eventos pendentes e publicador, telemetria, health checks, Problem Details |

O código fica em `src/<Camada>/<Context>/<Tipo>`:

| Camada | Conteúdo | Pode depender de |
|---|---|---|
| `Domain` | Entidades, value objects, eventos, exceções e interfaces de repositório. PHP puro, sem Laravel. | Nada |
| `Application` | Casos de uso (`<Ação>UseCase::execute()`), DTOs de entrada e saída e ports. PHP puro. | Domain |
| `Infrastructure` | Adapters das ports: Eloquent, Redis, RabbitMQ, JWT, Notifications, Reverb, OpenTelemetry e comandos de console | Application, Domain, framework |
| `Interfaces` | HTTP: controllers, FormRequests, Resources, Policies e middlewares | Application, Domain, framework |

Os contexts não importam uns aos outros, só o Shared, e se comunicam por eventos. O Deptrac garante as duas regras, e o PHPStan proíbe helpers do framework (`now()`, `config()`, facades) no Domain e na Application.

Os ids são UUIDv7 gerados no domínio e gravados em `BINARY(16)`.

## Containers

| Container | Papel |
|---|---|
| **API** (Octane + Swoole) | REST em `/api/v1`, a página de notificações e o OpenAPI. Os workers mantêm o app carregado entre requests. |
| **Publicador de eventos** (relay) | Lê os eventos pendentes em lotes e publica no RabbitMQ, com confirmação do broker |
| **Consumidor de decisões** | Lê a fila do Notification e entrega e-mail, notificação no banco e broadcast. Roda em 2 processos por padrão. |
| **Reverb** | Servidor WebSocket dos canais privados `users.{id}` |
| **MySQL primário** | Dados de todos os contexts, os eventos pendentes e o registro de eventos processados |
| **MySQL réplica** | Réplica de leitura assíncrona por GTID |
| **Redis** | Cache de pedidos e do papel, tokens revogados e rate limit do login |
| **RabbitMQ** | Exchange topic `travel_request.events` e as filas quorum do Notification |
| **Mailpit** | Servidor SMTP de desenvolvimento, com interface web |

## Fluxos

### Autenticação

1. `POST /auth/login` confere a senha (Argon2id) e devolve:
   - um **access token** JWT RS256 de 15 min, com `iss`, `aud`, `sub`, `jti`, `nbf` e `exp`, sem papel nem dados pessoais;
   - um **refresh token** opaco de 7 dias. O banco guarda só o hash SHA-256 dele, junto com a família.
2. A cada request, o guard confere a assinatura, pela chave pública com `kid`, publicada em `/.well-known/jwks.json`, e as claims. Depois consulta no Redis se o `jti` foi revogado e lê o papel do usuário (em cache por 300 s).
3. `POST /auth/refresh` troca o refresh por um par novo. Cada refresh vale uma vez, e reusar um já trocado revoga a família inteira.
4. `POST /auth/logout` revoga o access token (lista no Redis até expirar) e o refresh.

O cadastro pela API cria só usuários comuns. Administradores são criados por comando (`make admin`) ou pelo seeder.

### Criação e consulta

1. `POST /travel-requests` valida a entrada no FormRequest, cria o agregado na versão 1 e grava a linha com o solicitante vindo do token. A resposta é 201 com `ETag: "1"`.
2. Depois do commit, as listas em cache do dono e do admin são invalidadas: um contador de geração no Redis é incrementado.
3. `GET /travel-requests/{id}` e `GET /travel-requests` leem do cache no Redis. Num miss, leem do primário e guardam o resultado. A listagem usa paginação keyset por `id`.

### Decisão e notificação

1. **Decisão:** o admin chama `POST /travel-requests/{id}/approve` (ou `/cancel`) com `If-Match: "<versão>"`.
2. **Uma transação:** a API lê o pedido, faz `UPDATE … WHERE id = ? AND version = ?`, lê o nome e o e-mail do solicitante e grava o evento nos pendentes de publicação (`outbox_messages`). A decisão e o evento são confirmados juntos.
3. **Depois do commit:** o cache do pedido e das listas é invalidado, e a resposta é 200 com o novo `ETag`.
4. **Publicador de eventos:**
   - pega até 100 linhas pendentes com `SELECT … FOR UPDATE SKIP LOCKED`, num polling de 200 ms a 2 s;
   - publica cada uma como CloudEvent (`travel_request.approved.v1`) no exchange `travel_request.events` e espera o confirm do broker;
   - marca `published_at`.
5. **Consumidor:**
   - recebe da fila quorum `notification.travel_request`;
   - numa transação, grava o `event_id` no registro de eventos processados (`processed_events`), cria a notificação e envia o e-mail. Se o evento já está no registro, não entrega de novo.
6. **Broadcast:** depois do commit, o consumidor transmite `travel_request.decided` no canal privado do solicitante pelo Reverb. Se o Reverb falhar, a notificação continua disponível em `GET /notifications`.
7. **Ack:** a mensagem só é confirmada depois de resolvida.

O evento carrega os dados de que o consumidor precisa (*evento gordo*), inclusive o nome e o e-mail do solicitante. Assim o Notification não lê tabelas de outro context.

### Falhas na entrega

- Uma falha desfaz a transação do consumidor, e a mensagem vai para uma fila de espera com TTL: 10 s, 1 min e 5 min.
- Quando o TTL vence, a fila de espera devolve a mensagem para a fila principal.
- Esgotadas as tentativas, ou se o evento é inválido, a mensagem vai para `notification.travel_request.dlq`, com o motivo nos headers.
- Um alerta no Grafana avisa quando a DLQ tem mensagens. `make dlq` mostra o motivo, e `make dlq-replay` reprocessa.

## Concorrência

Cada pedido tem uma `version`, exposta como `ETag`. As escritas exigem `If-Match`:

| Situação | Resposta |
|---|---|
| Sem `If-Match` | 428 `/problems/precondition-required` |
| Versão diferente da atual | 412 `/problems/version-mismatch` |
| Transição proibida (ex.: cancelar aprovado) | 409 `/problems/invalid-status-transition` |
| Pedido de outra pessoa | 404, sem revelar que existe |
| Usuário comum tentando decidir | 403, antes de qualquer leitura |

O `UPDATE` condicional na versão garante que, de duas decisões simultâneas, só uma vence. O teste de carga de concorrência coloca 20 admins disputando cada um de 50 pedidos e confere que há exatamente uma vitória por pedido.

## Dados

| Tabela | Context | Conteúdo |
|---|---|---|
| `users` | Identity | Nome, e-mail, hash Argon2id, papel |
| `refresh_tokens` | Identity | Hash do refresh token, família, expiração, revogação |
| `travel_requests` | TravelRequest | Destino, datas, status, versão; FKs `requester_id` e `decided_by` para `users` |
| `outbox_messages` | Shared | Eventos pendentes de publicação, com exchange, routing key, payload e headers |
| `processed_events` | Notification | Registro de eventos já processados, por consumidor |
| `notifications` | Notification | Notificações do usuário, com `read_at` |

### Cache e réplica

- **Cache no Redis:** detalhe por id (300 s) e listas por geração (30 s). A invalidação acontece depois do commit, então uma transação desfeita não limpa nada.
- **Réplica:** recebe as leituras que toleram defasagem (as notificações). O lado de comando, a autenticação e o miss do cache leem do primário, porque uma réplica atrasada poderia recolocar no cache um estado antigo.
- **Durabilidade:** o primário tem commit durável, e a réplica roda sem binlog próprio, porque pode ser refeita com `make db-replica-sync`.

## Observabilidade

- O OpenTelemetry é a porta única: traces, métricas e logs saem por OTLP para o Collector.
- O Collector distribui para Jaeger (traces), Prometheus (métricas) e Loki (logs), todos consultados no Grafana.
- **Trace ponta a ponta:** o `traceparent` da request é gravado junto com o evento pendente. O publicador abre o span de publish como filho dele, e o consumidor abre o de consume como filho do publish. Assim, uma aprovação aparece num único trace, do HTTP à entrega.
- **Métricas:**
  - latência por rota;
  - pedidos criados e decididos;
  - eventos pendentes de publicação;
  - entregas por resultado;
  - latência ponta a ponta da notificação;
  - defasagem e estado da réplica;
  - filas do RabbitMQ;
  - CPU e memória por container.
- **Alertas:** DLQ com mensagens, réplica atrasada e replicação parada.
- O tracing fica desligado por padrão. `make obs` sobe o perfil e liga o tracing.

## Decisões e interpretações do enunciado

**Pontos em aberto no enunciado:**
- **O admin pode aprovar o próprio pedido:** o enunciado só impede o solicitante de mudar o status ([0003](adr/0003-regras-de-negocio-do-pedido.md)).
- **"Listar todos os pedidos" vale para o admin:** o usuário comum vê só os próprios, como pede o requisito de relacionamento ([0003](adr/0003-regras-de-negocio-do-pedido.md)).
- **O nome do solicitante vem do usuário autenticado**, não do corpo do request ([0034](adr/0034-relacionamento-pedido-usuario.md)).
- **"Atualizar o status" é feito por ações explícitas** (`/approve` e `/cancel`), não por um campo editável ([0016](adr/0016-convencoes-da-api.md)).
- **"Cancelar após aprovação" é uma regra de estado:** só um pedido `requested` muda de status ([0003](adr/0003-regras-de-negocio-do-pedido.md)).

**Escolhas conscientes:**
- **Sem rate limit na API autenticada:** de propósito, para os testes de carga medirem o teto real. O login tem rate limit ([0008](adr/0008-papeis-do-redis.md)).
- **O evento leva dados pessoais (nome e e-mail) pelo broker**, para o consumidor não depender do banco de outro context ([0007](adr/0007-mensageria-rabbitmq-e-outbox.md)).
- **Cadastro com e-mail já existente responde 409**, o que revela que o e-mail está cadastrado ([0030](adr/0030-detalhes-do-identity.md)).
- **Evolução possível:** trocar a emissão de tokens por um IdP externo (por exemplo, Keycloak), mudando só o adapter ([0005](adr/0005-autenticacao-jwt.md)).
- **A página de notificações substitui um frontend completo:** a finalidade é só mostrar a chegada da notificação ([0044](adr/0044-pagina-de-notificacoes.md)).

## Diagramas

Os diagramas seguem o modelo C4, feitos no [LikeC4](https://likec4.dev) a partir do modelo em [architecture/](architecture). Para navegar, rode `make docs` e abra http://localhost:5173. Nos fluxos (5.x), o botão **Start** percorre o fluxo passo a passo, e cada passo mostra os dados reais que passaram por ele: request, SQL, linha do outbox, CloudEvent, e-mail e evento do WebSocket.

| Visão | Conteúdo |
|---|---|
| 1. Contexto | Quem usa o serviço e com o que ele conversa |
| 2. Containers | Processos e armazenamentos do perfil padrão |
| 3. Observabilidade | Como traces, métricas e logs chegam ao Grafana |
| 4. Componentes da API | Os contexts dentro da API, por camada |
| 4.1 Identity | Login, refresh, autenticação e papel em cache |
| 4.2 TravelRequest | Do controller ao agregado, com cache, eventos pendentes e versionamento |
| 4.3 Relay do outbox | O publicador de eventos: envio ao RabbitMQ e gauges de saúde |
| 4.4 Consumidor de decisões | Registro de eventos processados, banco e e-mail numa transação, broadcast depois do commit |
| 5.1 Login, refresh e request autenticada | Fluxo de autenticação |
| 5.2 Criar e consultar um pedido | Criação, ETag e listagem com cache |
| 5.3 Aprovação → outbox → RabbitMQ → notificação | O fluxo central da decisão |
| 5.4 Falha na entrega | Retry e dead letter queue |
| 5.5 Um trace ponta a ponta | Propagação do trace pela mensageria |
| 6. Deployment no Docker Compose | Containers por perfil |
