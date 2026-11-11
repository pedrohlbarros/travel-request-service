# Resultados dos testes de carga

Medições da fase 07, feitas em 2026-11-11, com as metas do ADR 0041 e as otimizações do ADR 0042. Tudo roda na mesma máquina, e os números dependem do hardware: servem de referência, não de garantia.

## Ambiente

| Item | Valor |
|---|---|
| CPU | AMD Ryzen 5 5600GT, 6 núcleos / 12 threads |
| Memória | 14 GiB |
| CPU por grupo (`cpuset`) | API em 6 threads (`0-2,6-8`), k6 em 2 (`5,11`), demais serviços em 4 (`3-4,9-10`) |
| App | `php:8.5-cli-alpine`, Swoole 6.2.5, Octane com `max-requests=500`. Workers: 6 (`auto`) na base e 24 no final |
| MySQL | 8.4, primário durável (`sync_binlog=1`, `innodb_flush_log_at_trx_commit=1`) e réplica de leitura |
| Consumidores do Notification | 4 processos |
| SDK OTel | desligado, salvo nas rodadas de custo do SDK (`make obs-measure` deixa no ar só o Prometheus, o Grafana e o cAdvisor) |
| Dados | `make load-reset` antes de cada rodada: 55 usuários de carga e 10 mil pedidos |

## Metas (ADR 0041)

Carga de projeto de 100 req/s, cerca de 60× o pico estimado de uma empresa de 100 mil funcionários. As metas por operação são:
- leitura: p95 ≤ 150 ms e p99 ≤ 300 ms;
- escrita: p95 ≤ 250 ms e p99 ≤ 500 ms.

Valem ainda: erros < 0,1%, CPU ≤ 70% e notificação ponta a ponta com p95 ≤ 5 s. A mistura continua 70% leitura e 30% escrita.

## Linha de base × final

### Load: carga de projeto, 100 req/s por 5 min

| Métrica | Base | Final | Variação | Meta |
|---|---|---|---|---|
| Vazão | 99,3 req/s | 100,6 req/s | | 100 req/s |
| Leitura p95 | 31,7 ms | 14,4 ms | **−55%** | ≤ 150 ms |
| Leitura p99 | 95,2 ms | 27,7 ms | **−71%** | ≤ 300 ms |
| Escrita p95 | 99,1 ms | 114,7 ms | +16% | ≤ 250 ms |
| Escrita p99 | 287,9 ms | 464,1 ms | +61% | ≤ 500 ms |
| Erros | 0% | 0% | | < 0,1% |
| CPU da API (média / máximo) | — | 0,78 / 1,12 de 6 threads (13% / 19%) | | ≤ 70% |
| Notificação ponta a ponta | p95 de 2 s, máximo de 2 s | máximo de 1 s | | p95 ≤ 5 s |
| Fila do Notification no fim | 0 | 0 | | sem crescer |

- Todas as metas passam, exceto um ponto: no final, o p99 do **cancelamento** ficou em 500,2 ms, ou seja, 0,2 ms acima da meta. O p99 das escritas é a métrica mais instável. Ele depende de poucos commits lentos no disco e de esperas de lock entre escritas no mesmo pedido (ver "Contenção de escrita").
- Numa repetição de 2 min com a mesma configuração final, a escrita deu p95 de 85 ms e p99 de 328 ms, e a leitura, p95 de 14 ms e p99 de 20 ms.

### Capacity: 50 VUs por 5 min

| Métrica | Base | Final | Variação |
|---|---|---|---|
| Vazão | 326,8 req/s | **484,3 req/s** | **+48%** |
| Leitura p95 / p99 | 264 / 352 ms | 169 / 234 ms | −36% / −34% |
| Escrita p95 / p99 | 321 / 408 ms | 233 / 313 ms | −27% / −23% |
| Erros | 0% | 0% | |
| CPU da API (média) | 3,15 de 6 | 5,60 de 6 | a API passou a usar a CPU que tem |
| CPU da réplica (média) | 0,43 | 0,09 | −79% |

### Stress: 50 → 400 VUs, estágios de 2 min

| VUs | Base: req/s, leitura p95, escrita p95 | Final: req/s, leitura p95, escrita p95 |
|---|---|---|
| 50 | 301, 178 ms, 232 ms | **403, 109 ms, 209 ms** |
| 100 | 342, 351 ms, 404 ms | 335, 329 ms, 1772 ms |
| 200 | 338, 642 ms, 683 ms | 336, 994 ms, 2626 ms |
| 300 | 337, 997 ms, 1036 ms | 282, 3088 ms, 4547 ms |
| 400 | 319, 1427 ms, 1478 ms | 345, 4282 ms, 5575 ms |

- **Teto** (último estágio com leitura p95 ≤ 150 ms e erros < 0,1%):
  - na base, nenhum: a leitura já passava da meta com 50 VUs;
  - no final, 50 VUs com 403 req/s, cerca de 4× a carga de projeto.
- **Acima do teto, o final degrada pior nas escritas**, e houve 2 erros 5xx em cerca de 40 mil requests no estágio de 100 VUs (`Lock wait timeout`, depois de 5 s).

## Contenção de escrita

- Com a API saturada, os 24 workers mantêm mais transações abertas ao mesmo tempo. Quem segura o lock de uma linha espera CPU para chegar ao commit, e as escritas no mesmo pedido fazem fila atrás dele.
- No stress, o MySQL registrou 8.102 esperas de lock, com média de 185 ms e máximo de 6 s.
- A mistura do k6 força esse conflito: admins decidem a partir da mesma página de pendentes, e a edição disputa com a decisão.
- Na carga de projeto, isso quase não aparece: cerca de 100 esperas em 2 min, com média de 31–52 ms.
- O `innodb_lock_wait_timeout` de 5 s (ADR 0042) transforma uma espera longa em erro, em vez de prender o worker indefinidamente.

## Atribuição (capacity de 2 min, API em 6 threads)

Rodadas curtas, com ruído de ±10–15% entre execuções iguais.

| Passo | req/s | Leitura p95 | Escrita p95 | Efeito |
|---|---|---|---|---|
| Base (6 workers) | 309,6 | 254 ms | 299 ms | |
| + cache do papel na autenticação | 317,4 | 263 ms | 320 ms | +2,5%, dentro do ruído |
| + 12 workers | 436,0 | 198 ms | 270 ms | +37% |
| + 24 workers | 466,9 | 170 ms | 236 ms | **+47%** sobre a base |
| Réplica fora do caminho, ainda rodando | 454,3 | 172 ms | 242 ms | sem efeito mensurável |
| Réplica parada | 446,6 | 168 ms | 217 ms | sem efeito mensurável |

- Com 6 workers, a API usava em média 3,2 dos 6 núcleos mesmo saturada. Os workers passavam o tempo esperando o MySQL e o Redis.
- O `max-requests` alto (100 mil) não mudou nada: a reciclagem a cada 500 requests não é a causa das travadas.
- Os timeouts e o teste de idas por request são de robustez, sem efeito esperado na vazão.

### Custo do SDK OTel (24 workers)

| Configuração | req/s | Perda sobre o SDK desligado |
|---|---|---|
| SDK desligado | 456–467 | |
| Como era: pacote auto Laravel completo, flush por request, protobuf em PHP | 132,9 | −72% |
| + só HTTP e query | 157,1 | −66% |
| + export em lote (5 s) | 213,6, com p99 de 3,4 s | −54% |
| + extensão `protobuf` | **440,2** | **−3,4%** |

A serialização em PHP puro era o maior custo. Com o export em lote sem a extensão, cada lote de milhares de spans prendia o worker por segundos, daí o p99 de 3,4 s.

## Idas por request (cache quente)

Travadas no `RoundTripsPerRequestTest`:

| Rota | Queries | Comandos Redis |
|---|---|---|
| Listar pedidos | 0 | 4 |
| Detalhe | 0 | 3 |
| Listar notificações | 1 | 2 |
| Criar | 3 | 7 |
| Aprovar | 7 | 7 |

Dois cortes possíveis ficaram de fora (ADR 0042):
- os nomes do solicitante e de quem decidiu numa query só;
- o `GET` do cache que sempre dá miss na releitura depois de uma escrita.

## Smoke e concorrência

| Cenário | Resultado |
|---|---|
| Smoke (1 VU, 30 s) | 100% dos checks, p95 de 25 ms |
| Concorrência (20 admins × 50 pedidos) | 50 vitórias, 950 conflitos (409/412), 0 status inesperados, todos os pedidos na versão 2 |

## Primeira rodada (antes das metas)

Feita sem CPU pinada, com 12 workers e a régua antiga (p95 < 200 ms com 50 VUs):

| Rodada | req/s | p95 | p99 | Teto (p95 < 500 ms) |
|---|---|---|---|---|
| Alpine, réplica, 4 consumidores | 519 | 213 ms | 303 ms | 200 VUs, 531 req/s |
| Alpine, sem réplica, 1 consumidor | 630 | 149 ms | 258 ms | 200 VUs, 524 req/s |
| Debian, sem réplica, 1 consumidor | 567 | 154 ms | 320 ms | 100 VUs, 492 req/s |
| Alpine, SDK ligado | 261 | 289 ms | 412 ms | não medido |

- **Alpine × Debian:** a Debian ficou 10% abaixo em req/s. Pela regra do ADR 0039 (troca só com mais de 15% de ganho), **a imagem continua Alpine**.
- **Commit:** 12 ms de mediana (p95 de 20 ms) com 2 fsyncs no primário; 0,12 ms com o commit relaxado, que fica só como comparação.
- **Consumidor:** cerca de 8 mensagens/s com 1 processo e 47/s com 4, sem duplicar nada graças ao inbox.

## Como reproduzir

```sh
make up && make load-seed
# no .env: APP_CPUSET=0-2,6-8, K6_CPUSET=5,11, BACKING_CPUSET=3-4,9-10 (ajuste para a sua CPU)
NOTIFICATION_CONSUMERS=4 make obs-measure
make load-reset && make load-load RUN=minha-rodada && make load-pipeline
make load-reset && make load-capacity RUN=minha-rodada
make load-reset && make load-stress RUN=minha-rodada
make load-concurrency && make load-smoke
```

Os resumos brutos ficam em `build/load/<cenário>-<rótulo>.json` (fora do git). Para medir o custo do SDK, troque o `make obs-measure` por `make obs`.
