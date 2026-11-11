# 0042 — Otimização de desempenho

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0005](0005-autenticacao-jwt.md), [0008](0008-papeis-do-redis.md), [0009](0009-infra-docker-e-perfis.md), [0030](0030-detalhes-do-identity.md), [0038](0038-detalhes-da-observabilidade.md), [0039](0039-detalhes-dos-testes-de-carga.md), [0040](0040-replica-de-leitura.md), [0041](0041-metas-de-desempenho-e-carga-de-projeto.md)

## Contexto
O ADR 0041 fixou as metas e a carga de projeto. A leitura do código e da primeira rodada mostrou estes pontos:
- **Autenticação:** toda request autenticada relê o usuário no primário para saber o papel, porque o JWT não carrega a role (ADR 0005).
- **Réplica:** recebe quase nenhuma leitura. Os repositórios e o miss do cache vão ao primário (ADR 0040), e ainda assim ela aplica o binlog e grava em disco na mesma máquina.
- **CPU compartilhada:** API, bancos, consumidores e o k6 disputam as mesmas CPUs, e os números ficam misturados.
- **SDK OTel:** tira 59% da vazão. São três causas:
  - o export serializa em protobuf com PHP puro, porque a extensão não está na imagem;
  - o flush faz um POST síncrono ao Collector depois de cada request, com o worker preso nele;
  - a auto-instrumentação do Laravel gera um span por operação Eloquent, que repete o span da query, e um por comando Redis.
- **Dashboard:** o Grafana não mostra a CPU por container nem a latência ponta a ponta da notificação, não desenha as metas e não alerta quando a replicação para.
- **I/O bloqueante:** o Octane com Swoole atende uma request por worker de cada vez, e o PDO bloqueia o worker durante cada query.

## Decisão
### Instrumentos de medição
- **Isolamento de CPU:** `cpuset` por grupo de serviços via `.env`, vazio por padrão (sem limite no uso normal):
  - `APP_CPUSET` para a API;
  - `K6_CPUSET` para o k6;
  - `BACKING_CPUSET` para os bancos, o Redis, o RabbitMQ, o relay, os consumidores, o Mailpit, o Reverb e o perfil `obs`.

  As rodadas de medição reservam os núcleos para que a API não dispute CPU com o k6 nem com os consumidores. É o mais perto de máquinas separadas que dá para medir num host só.
- **CPU e memória por container:** o cAdvisor entra no perfil `obs`, raspado pelo Collector. O dashboard ganha CPU por serviço, sobre os núcleos dele e com a linha dos 70%, e a memória por container.
- **Linha de SLO:** p95 e p99 de leitura e de escrita com as metas desenhadas e a taxa de erro em % contra 0,1%.
- **Latência ponta a ponta:** histograma `notification.end_to_end.duration`, que o consumidor registra do `time` do evento até a entrega, com painel e a meta de 5 s. Nas rodadas sem o SDK, a mesma medida sai de um relatório SQL depois do load, comparando a decisão do pedido com a criação da notificação.
- **Replicação parada:** o relay exporta também se as threads da réplica estão rodando (`mysql.replica.running`), e um alerta separado dispara quando ela para.
- **Makefile:**
  - `make obs-measure` sobe o `obs` com o SDK desligado, só para as métricas do k6 e do cAdvisor;
  - `make load-capacity` roda os 50 VUs;
  - `make load-pipeline` mostra a latência ponta a ponta pelo banco e as filas;
  - `DURATION=`, `VUS=` e `LOAD_RATE=` ajustam os cenários.

### Otimizações
- **Cache da autenticação:** a autenticação passa a ler só o papel, por uma port própria (`UserRoles`), em vez de reconstituir o usuário inteiro. O adapter lê só a coluna `role` no primário, e um decorator guarda o papel no Redis (`IDENTITY_ROLE_CACHE_TTL`, 300 s). O cache não leva o hash da senha nem dados pessoais. O papel só é definido na criação do usuário, e nada o altera depois, então não há o que invalidar: o TTL só limita uma mudança feita direto no banco. Usuário inexistente não vai para o cache.
- **Idas por request:** um teste conta as queries e os comandos Redis de cada rota com o cache quente e trava esses números, para que um aumento não passe despercebido. A auditoria achou dois cortes: carregar o solicitante e quem decidiu numa query só, e pular o `GET` do cache que sempre dá miss na releitura depois de uma escrita. Os dois mexeriam no ADR 0034 e numa port por um ganho estimado de 1–3%, abaixo do ruído da medição, e ficaram de fora.
- **Timeouts:** uma dependência lenta vira erro rápido em vez de prender todos os workers. Os valores vêm do `.env`:
  - MySQL: conexão em 2 s (`PDO::ATTR_TIMEOUT`); `max_execution_time` de 5 s para SELECT e `innodb_lock_wait_timeout` de 5 s, pela sessão (`INIT_COMMAND`). O PDO não tem timeout de leitura de rede, então um servidor que trava sem responder fica fora;
  - Redis: conexão e resposta em 1 s (`timeout` e `read_timeout` do phpredis).
- **Workers:** rodadas de capacity com a CPU da API reservada (6 threads), testando 6, 12 e 24 workers. Com 24, a API entregou 47% a mais que com 6, e a CPU dela chegou perto do limite, então mais workers não teriam ganho. O padrão do `OCTANE_WORKERS` passa a ser 24. Sem pinagem, isso dá 2× os 12 threads da máquina, o que continua seguro: com I/O bloqueante, mais workers que núcleos ajudam até a CPU saturar.
- **SDK OTel enxuto:**
  - a extensão `protobuf` entra na imagem;
  - o export de spans e logs passa a ser em lote por tempo ou por tamanho, verificado no fim de cada unidade de trabalho, em vez de um POST por request. O que sobrar sai quando o worker para;
  - o export de spans e logs usa o `OTEL_BSP_SCHEDULE_DELAY` (5 s) como intervalo;
  - o pacote auto Laravel fica desligado (`OTEL_PHP_DISABLED_INSTRUMENTATIONS=laravel`). A app registra, pelo SPI do composer, a própria instrumentação (`LaravelHttpAndQueryInstrumentation`), que reaproveita o hook de HTTP e os watchers de query, cache, exceção e log do pacote. Os spans de Model e de Redis deixam de existir, e os eventos de cache ficam no span HTTP;
  - a amostragem continua 100%.
- **Réplica:** com a réplica no caminho, fora dele (`APP_DB_READ_HOST=mysql`) ou parada, a vazão ficou igual, dentro do ruído. Ela roda nos núcleos dos serviços de apoio e não disputa CPU com a API, por isso fica no compose, pronta para leituras que não passem pelo cache.

  Ela gastava mais CPU que o primário, porque o MySQL 8.4 grava por padrão um binlog na réplica e faz 2 fsyncs por transação aplicada. Como a réplica se refaz do primário (`make db-replica-sync`), ela passa a rodar sem binlog próprio (`skip-log-bin`), com `sync_binlog=0` e `innodb_flush_log_at_trx_commit=2`. O primário continua durável.
- **Consumidor:** a entrega em lote só é discutida se os números com a CPU isolada mostrarem que os consumidores pesam na latência de escrita da API.

### Fora
- Corrotinas no PDO (hooks do Swoole): o Octane não atende requests concorrentes no mesmo worker, e isso quebraria a regra de nenhum estado por request.
- O commit relaxado como padrão: o oficial continua durável (ADR 0041).
- O JIT do OPcache: no Octane o código já fica carregado, e o serviço é dominado por I/O.

### Medição
- **Linha de base:** load, capacity e stress com o código anterior à otimização e os instrumentos acima.
- **Atribuição:** um capacity de 1 min a cada otimização ligada, para saber quanto cada uma contribui.
- **Final:** load, capacity e stress com tudo ligado, a rodada com e sem réplica e o capacity com o SDK ligado para o custo da observabilidade.
- Os resultados ficam em `docs/load/resultados.md`, com a % de melhoria de cada métrica sobre a linha de base.

## Consequências
- Uma troca de papel fora do comando que apaga a chave (por exemplo, um `UPDATE` direto no banco) só vale quando a chave expira.
- Sem o pacote auto Laravel no registro automático, uma atualização dele precisa conferir se as classes reaproveitadas continuam com a mesma assinatura.
- No export em lote, um worker que morre sem shutdown perde o último lote de spans (até 5 s).
- A extensão `protobuf` precisa acompanhar a versão do `google/protobuf` do composer.
- Uma réplica que cai pode perder as últimas transações aplicadas e precisar de `make db-replica-sync`.
- O cAdvisor precisa de acesso de leitura ao Docker do host, então fica só no perfil `obs`.
