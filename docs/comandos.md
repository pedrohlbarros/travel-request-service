# Comandos

Toda ação do projeto passa pelo `Makefile` e roda dentro dos containers, então o host só precisa de Docker e `make`. `make help` lista os alvos por seção.

As portas e as credenciais vêm do `.env`. Os exemplos abaixo usam os valores padrão do [.env.example](../.env.example).

## Ambiente

| Comando | O que faz |
|---|---|
| `make setup` | Primeira execução: cria o `.env`, builda a imagem, sobe os containers, roda o `composer install`, gera a `APP_KEY`, sincroniza a réplica, aplica as migrations, gera as chaves JWT e instala os git hooks. Pode ser repetido. |
| `make up` | Sobe o perfil padrão e espera os containers ficarem saudáveis. Os outros perfis sobem pelos próprios alvos (`make obs`, `make docs`, `make load-*`). |
| `make down` | Remove os containers de todos os perfis (padrão, obs, load, docs e debian); os volumes (dados) ficam |
| `make restart` | Reinicia todos os serviços |
| `make build` | Rebuilda a imagem do app, depois de mudar o `Dockerfile` ou a configuração do PHP |
| `make ps` | Estado e saúde de cada container |
| `make logs` | Acompanha os logs de todos; `make logs S=app` de um só |
| `make urls` | IP no container, porta no host e URL de cada serviço |
| `make shell` | Abre um shell no container do app |
| `make composer ARGS="require vendor/pacote"` | Roda o Composer no container |

## Banco e réplica

| Comando | O que faz |
|---|---|
| `make db-replica-sync` | Refaz a réplica de leitura a partir de um dump do primário e liga a replicação. Use num ambiente antigo ou se a replicação quebrar. |
| `make db-replica-status` | Mostra se a réplica está replicando e quantos segundos está atrás |

## Usuários e autenticação

| Comando | O que faz |
|---|---|
| `make seed` | Cria as contas de demonstração `admin@example.com` (admin), `luffy@example.com` e `zoro@example.com` (usuários), todas com a senha `password123` |
| `make user` | Cria um usuário comum; pede o que faltar, ou `NAME="..." EMAIL=... PASSWORD=...` |
| `make admin` | Cria um administrador, da mesma forma. Admin não é criado pela API. |
| `make keys` | Gera as chaves RS256 do JWT em `storage/keys`, se ainda não existem |
| `make keys-rotate` | Troca as chaves; todo access token emitido deixa de valer |

## Demonstração

| Comando | O que faz |
|---|---|
| `make demo` | Percorre o fluxo inteiro e imprime cada passo: login de Luffy e do admin, criação de um pedido, aprovação com `If-Match`, notificação entregue e e-mail no Mailpit |
| `make web` | Mostra a URL da página de notificações ao vivo (`/docs/notifications`, só com `APP_ENV=local`). Entre como Luffy e rode `make demo` para ver a decisão chegar. |

Para explorar a API passo a passo, use a coleção [api/requests.http](api/requests.http), que roda no HTTP Client do PhpStorm.

## API

| Comando | O que faz |
|---|---|
| `make openapi` | Exporta o OpenAPI para [api/openapi.json](api/openapi.json). A documentação navegável fica em http://localhost:8000/docs/api (só com `APP_ENV=local`). |

## Mensageria

| Comando | O que faz |
|---|---|
| `make events` | Mostra os eventos publicados no exchange `travel_request.events` por uma fila de inspeção. A primeira execução cria a fila; `LIMIT=20` limita a quantidade. |
| `make outbox` | Quantos eventos estão pendentes de publicação e quantos já foram publicados |
| `make relay-logs` | Acompanha os logs do publicador de eventos |

## Notificações

| Comando | O que faz |
|---|---|
| `make dlq` | Lista as decisões na dead letter queue com o motivo, sem tirá-las da fila |
| `make dlq-replay` | Devolve as decisões da DLQ para a fila principal |
| `make consumer-logs` | Acompanha os logs do consumidor de notificações |
| `make reverb-logs` | Acompanha os logs do Reverb (WebSocket) |
| `make mailpit` | Mostra a URL do Mailpit, onde ficam os e-mails enviados |

## Observabilidade

| Comando | O que faz |
|---|---|
| `make obs` | Sobe o perfil `obs` (Collector, Jaeger, Prometheus, Loki, Grafana e cAdvisor) e liga o tracing no app, no relay e no consumidor |
| `make obs-measure` | Sobe o mesmo perfil com o tracing desligado, só para as métricas do k6 e dos containers durante os testes de carga |
| `make obs-down` | Derruba o perfil e desliga o tracing |
| `make obs-urls` | Mostra as URLs do Grafana, Jaeger, Prometheus e Loki |

## Qualidade e testes

O detalhe de cada suíte e das ferramentas está no [guia de testes](testes.md).

| Comando | O que faz |
|---|---|
| `make qa` | Tudo o que o CI roda: `lint`, `stan`, `arch`, todas as suítes com cobertura e Infection |
| `make lint` / `make lint-fix` | Confere e corrige o estilo (Pint, PER) |
| `make stan` | Análise estática (PHPStan + Larastan, nível max) |
| `make arch` | Regras de arquitetura (Deptrac) |
| `make test` | Todas as suítes |
| `make test-unit` | Domain e Application, com fakes em memória |
| `make test-integration` | Adapters contra MySQL, Redis e RabbitMQ reais |
| `make test-feature` | Endpoints HTTP |
| `make test-e2e` | Contra o servidor Octane no ar, com relay e consumidor |
| `make test-tools` | Regras próprias do PHPStan |
| `make coverage` | Todas as suítes com cobertura; falha abaixo de 100% |
| `make infection` | Mutation testing em Domain e Application; falha abaixo de 100% de MSI |

## Testes de carga

O detalhe de cada cenário, as metas e os resultados estão em [testes-de-carga.md](testes-de-carga.md).

| Comando | O que faz |
|---|---|
| `make load-seed` | Cria 50 usuários e 5 admins de carga e completa até 10 mil pedidos |
| `make load-reset` | Volta os dados de carga ao estado do seed e esvazia as filas do Notification |
| `make load-smoke` | 1 VU por 30 s |
| `make load-load` | Carga de projeto: cerca de 100 req/s por 5 min |
| `make load-capacity` | 50 VUs por 5 min, o mais rápido possível |
| `make load-stress` | 50 → 400 VUs em estágios de 2 min, com o teto no resumo |
| `make load-concurrency` | 20 admins × 50 pedidos; exatamente uma decisão vence |
| `make load-pipeline` | Latência da decisão até a notificação e mensagens ainda nas filas |
| `make load-debian-up` / `make load-debian-down` | Sobe e derruba a variante Debian do app, para comparar com a Alpine (`TARGET=debian`) |

Opções dos cenários:
- `RUN=<rótulo>` nomeia o resumo em `build/load/`;
- `DURATION=2m`, `VUS=` e `LOAD_RATE=` (iterações por segundo) ajustam o cenário;
- `TARGET=debian` aponta para a variante Debian.

Com o perfil `obs` no ar, as métricas do k6 vão para o Prometheus e aparecem no dashboard "k6 Prometheus" do Grafana.

## Documentação

| Comando | O que faz |
|---|---|
| `make docs` | Sobe os diagramas de arquitetura (perfil `docs`) |
| `make docs-down` | Derruba os diagramas |
