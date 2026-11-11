# 0039 — Detalhes dos testes de carga

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0009](0009-infra-docker-e-perfis.md), [0018](0018-padrao-de-testes.md), [0022](0022-imagem-base-e-versoes.md), [0038](0038-detalhes-da-observabilidade.md)

## Contexto
A fase 07 mede o serviço com o k6. O ADR 0018 fixa os quatro cenários e os thresholds do load (p95 < 200 ms, erros < 1%). Ficam em aberto:
- o que cada usuário virtual faz;
- de onde vêm os dados;
- como ler o stress e montar a corrida de concorrência;
- como medir sem que a própria observabilidade distorça os números;
- o limiar para trocar a imagem Alpine pela Debian (ADR 0022).

## Decisão
### Cenários (`tests/Load`)
- **Smoke:** 1 VU por 30 s, com a mesma mistura do load. Exige 100% dos checks, erros < 1% e p95 < 500 ms.
- **Load:** 50 VUs por 5 min. Cada iteração sorteia uma ação:
  - 50%: listar os próprios pedidos, às vezes filtrando `status=requested`, e seguir para a próxima página;
  - 20%: consultar um detalhe;
  - 15%: criar um pedido;
  - 5%: editar o destino com `If-Match`;
  - 10%: um admin decide um pedido pendente.
  - Thresholds: p95 < 200 ms no geral e por operação, erros < 1% e checks > 99%.
  - Na decisão e na edição, 409 e 412 são resultados esperados: admins disputam os mesmos pedidos, e uma edição pode perder para uma decisão que chegou entre o GET e o PATCH. É o controle otimista do ADR 0006 funcionando, não erro do serviço.
- **Stress:** 10 → 400 VUs em estágios de 2 min (50, 100, 200, 300, 400), com a mesma mistura. Cada request leva o estágio como tag. O teto é o último estágio com p95 < 500 ms e erros < 1%, e o resumo registra RPS, p95 e p99 de cada estágio. Se os erros passam de 20%, o teste é interrompido.
- **Concorrência:** 20 VUs admin e 50 pedidos novos. Em cada rodada de 500 ms, todos disparam aprovar ou cancelar o mesmo pedido com `If-Match: "1"` no mesmo instante do relógio. Os thresholds exigem:
  - exatamente 50 vitórias (200);
  - nenhum status fora de 200/409/412;
  - no teardown, todo pedido na versão 2 e fora de `requested`. Um segundo vencedor teria levado a versão a 3, e com uma decisão só há um evento só no outbox.

### Dados
- `make load-seed` roda dois seeders, um por context, ambos idempotentes e recusados em produção:
  - `LoadTestUsersSeeder` (Identity): 50 usuários e 5 admins (`load-user-NN` e `load-admin-NN`, senha conhecida);
  - `LoadTestTravelRequestsSeeder` (TravelRequest): completa até 10 mil pedidos espalhados entre esses usuários, com 60% `requested` e 40% já decididos por um admin, viagens futuras e criação nos últimos 90 dias.
- As quantidades vêm de `config/load_test.php`.
- `make load-reset` volta os dados de carga ao estado do seed antes de cada rodada. Ele apaga os pedidos e as notificações dos usuários de carga e as linhas publicadas do outbox, purga as filas do Notification (são só dados de teste) e limpa o cache.
- O k6 faz login de cada usuário uma vez no `setup()`, dentro do rate limit de login.

### k6, métricas e dashboard
- O k6 roda no perfil `load` (`grafana/k6:2.2.0`). Os alvos são `make load-smoke`, `load-load`, `load-stress` e `load-concurrency`, com `RUN=<rótulo>` para nomear o resultado.
- Com o Prometheus no ar, o k6 envia as métricas por remote write (`--web.enable-remote-write-receiver`), com p(95) e p(99). O dashboard oficial do k6 para Prometheus (ID 19665) fica versionado e provisionado.
- Sem o `obs`, o k6 só imprime o resumo.
- O resumo bruto de cada rodada vai para `build/load/<cenário>-<rótulo>.json`, fora do git.

### Como medir
- **Rodada oficial:** o `obs` fica no ar só para o Prometheus e o Grafana do k6, com o SDK da app desligado.
- **Segunda rodada:** o load roda com o SDK ligado (`make obs`) para medir o custo da instrumentação.
- Os workers do Octane vêm do `.env` (`OCTANE_WORKERS`, padrão `auto`, um por CPU; `OCTANE_MAX_REQUESTS`, padrão 500). Os resultados registram CPU, memória e quantidade de workers.
- A medição oficial é uma rodada de load e uma de stress na Alpine, com o MySQL durável, a réplica de leitura e 4 consumidores. As comparações entram como rodadas únicas, medidas durante a fase:
  - Debian com as mesmas extensões;
  - o load com o SDK ligado;
  - o commit isolado com o MySQL relaxado (`DB_SYNC_BINLOG=0` e `DB_FLUSH_LOG_AT_TRX_COMMIT=2`).
  Repetir rodadas para tirar a mediana ficou fora por causa do tempo. Os resultados trazem a variação observada entre execuções.
- As rodadas usam 4 processos consumidores. Um único consumidor entrega cerca de 8 mensagens/s, contra cerca de 40 decisões/s no load. O padrão do compose é 2 (`NOTIFICATION_CONSUMERS`), e a fila quorum distribui as mensagens entre eles. O inbox garante que não haja entrega duplicada.
- Os números ficam em [docs/load/resultados.md](../load/resultados.md).

### Alpine × Debian
- A variante Debian (`docker/debian/Dockerfile`, serviço `app-debian` no perfil `debian`) tem as mesmas extensões e configurações. Ela sobe só para a comparação (`make load-debian-up`, e os cenários com `TARGET=debian`).
- Se a Debian for mais de 15% melhor em RPS no teto do stress ou em p95 do load, a imagem base muda, com um ADR novo. Abaixo disso, fica a Alpine.

### CI
O smoke e a concorrência rodam no CI depois do `make qa`, com `make load-seed` antes. O load e o stress ficam só locais: o runner tem poucas CPUs, e os números não valeriam.

## Consequências
- Os números dependem da máquina, e o teto escala com os workers. Os resultados sempre trazem o ambiente.
- O load deixa milhares de pedidos e notificações a mais no banco de dev. O `load-seed` só completa até o alvo, não apaga nada.
- A API autenticada continua sem rate limit, de propósito (ADR 0008), para o k6 medir o teto real.
- A leitura e a escrita ficaram em bancos separados por causa dos números desta fase (ADR 0040).
