# 0040 — Réplica de leitura do MySQL

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0001](0001-stack-e-runtime.md), [0008](0008-papeis-do-redis.md), [0009](0009-infra-docker-e-perfis.md), [0033](0033-detalhes-da-api-de-pedidos.md)

## Contexto
Os testes de carga da fase 07 mostraram que as escritas são o caminho mais lento. Um `COMMIT` no MySQL do compose leva cerca de 12 ms (p95 de 20 ms) por causa de dois fsyncs por transação, o do binlog e o do redo log, no volume do Docker. Ao mesmo tempo, toda request autenticada lê do mesmo banco. O pedido foi separar leitura e escrita em dois bancos, configurados na própria conexão do Laravel.

## Decisão
### Topologia
- O serviço `mysql` passa a ser o primário (`server-id` 1, GTID ligado). O `mysql-replica` (mesma imagem 8.4, `server-id` 2) replica dele de forma assíncrona por GTID, filtrando só o banco da aplicação (`--replicate-do-db`). O banco de testes nunca chega à réplica.
- `make db-replica-sync` (`docker/mysql/sync-replica.sh`) faz uma vez a base da réplica e pode ser repetido: cria a conta de replicação, apaga a réplica, carrega um dump consistente do primário (`--set-gtid-purged`) e liga a replicação com auto-position. A réplica fica em `super_read_only`. O `make setup` já roda esse alvo, e `make db-replica-status` mostra o estado.
- A porta da réplica no host vem do `.env` (`FORWARD_DB_REPLICA_PORT`, 3307).
- A durabilidade do commit no primário é configurável (`DB_SYNC_BINLOG` e `DB_FLUSH_LOG_AT_TRX_COMMIT`, padrão 1/1). Os testes de carga também medem com 0/2.

### Conexão no Laravel
- A conexão `mysql` usa `read`/`write` com `sticky`. Escritas e `SELECT ... FOR UPDATE` vão ao primário. As leituras da API vão à réplica (`DB_READ_HOST=mysql-replica`), e depois de uma escrita a própria request volta a ler do primário.
- O relay e o consumidor usam só o primário (`DB_READ_HOST=mysql`). A suíte de testes também, num banco só.
- Uma conexão `mysql_replica` dedicada lê o estado da réplica.

### O que lê de onde
- **Repositórios (lado de comando): sempre o primário.** Uma escrita decide com base no que lê, e logo depois de criar um pedido ou um usuário a réplica pode ainda não ter a linha. Aprovar um pedido recém-criado ou logar logo depois do cadastro daria 404 ou 401.
- **Cache de pedidos: o miss lê do primário.** A invalidação acontece no commit, e uma réplica atrasada recolocaria o estado anterior no cache até o TTL. Como quase toda leitura de pedido passa pelo cache, a réplica atende principalmente o `GET /notifications`.
- **Demais consultas:** vão à réplica e toleram a defasagem, de milissegundos no compose.

### Saúde e observabilidade
- O `/health/ready` ganha o check `mysql_replica`, que conecta e confere se as duas threads de replicação estão rodando.
- O relay exporta o gauge `mysql.replica.lag` (segundos, de `SHOW REPLICA STATUS`), uma série só, como o `outbox.pending`. O dashboard tem o painel de defasagem, e um alerta dispara quando ela passa de 5 s por 1 min.

### Repositório de pedidos
O `EloquentTravelRequestRepository` guarda a linha que entregou no `get()` e a reaproveita no `update()`, sem ler de novo antes do `UPDATE` condicional. Continua gravando só as colunas alteradas (ADR 0033). Uma instância nova é criada para cada caso de uso, então nada passa de uma request para outra.

## Consequências
- Mais um serviço no compose (sem perfil) e um passo a mais no setup. Um ambiente que já existia precisa de `make db-replica-sync` uma vez.
- A réplica alivia pouco nesta carga, porque o cache absorve as leituras de pedido. Ela fica pronta para leituras pesadas que não passem pelo cache, como relatórios.
- A replicação é assíncrona: se o primário cai, as últimas transações podem não ter chegado à réplica. A réplica não é promovida sozinha.
- Uma leitura que vá à réplica pode ver um estado de milissegundos atrás. Por isso o lado de comando e o preenchimento do cache ficam no primário.
