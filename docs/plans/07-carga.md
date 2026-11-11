# Fase 07 — Testes de carga

- **Status:** Concluído (aprovado em 2026-11-11)
- **ADRs:** 0008, 0009, 0011, 0018, 0022, 0038, 0039, 0040, 0041, 0042

## Objetivo
Medir e garantir performance e a garantia de concorrência com k6.

## Escopo
- Perfil `load` com k6 2.2; remote write para o Prometheus quando `obs` está no ar; dashboard oficial do k6 (19665) provisionado (ADR 0039).
- Cenários em `tests/Load`: smoke (1 VU/30 s), load (50 VUs/5 min, mistura realista, p95 < 200 ms, erros < 1%), stress (50 → 400 VUs em estágios de 2 min, teto = último estágio com p95 < 500 ms e erros < 1%), concorrência (20 admins × 50 pedidos → exatamente 1 vitória por pedido, resto 412/409, versão final 2).
- `make load-seed`: seeders por context (55 usuários de carga, ~10 mil pedidos), idempotentes.
- Workers do Octane por `.env` (`OCTANE_WORKERS`, `OCTANE_MAX_REQUESTS`).
- Makefile: `load-seed`, `load-smoke`, `load-load`, `load-stress`, `load-concurrency`, `load-debian-up`, `load-debian-down` (`RUN=` rótulo, `TARGET=debian`).
- Smoke e concorrência no CI depois do `make qa`.
- `make load-reset` entre rodadas; medição oficial em uma rodada (Alpine, MySQL durável, réplica, 4 consumidores); Debian, SDK e MySQL relaxado como rodadas únicas da fase.
- Réplica de leitura do MySQL (ADR 0040): `mysql-replica` por GTID, conexão read/write com sticky, repositórios e miss do cache no primário, readiness e gauge de defasagem com alerta; `make db-replica-sync` e `make db-replica-status`.
- Repositório de pedidos reaproveita a linha do `get()` no `update()`, sem ler de novo.
- Consumidores do Notification configuráveis (`NOTIFICATION_CONSUMERS`, padrão 2).
- Resultados em `docs/load/resultados.md`; resumos brutos em `build/load/`.
- **Ressalva musl (ADR 0022):** rodar load e stress na imagem Alpine e numa variante Debian temporária com as mesmas extensões; comparar RPS, p95/p99, uso de memória e teto do stress. Registrar os números para a entrega.

## Otimização (ADRs 0041 e 0042)
Depois da primeira rodada, a fase ganhou metas com base em referências de mercado e uma rodada de otimização, medida contra uma linha de base nova.
1. **Metas e carga de projeto (ADR 0041):** modelo de uso, 100 req/s, metas por leitura e escrita, CPU, pipeline, réplica e custo do SDK. O load passa a taxa constante; entra o capacity (50 VUs); o teto do stress passa a ser pela meta de leitura.
2. **Instrumentos:** CPU pinada por `.env`, cAdvisor e painéis de metas no Grafana, `notification.end_to_end.duration`, `mysql.replica.running` com alerta, `make obs-measure`, `load-capacity` e `load-pipeline`.
3. **Linha de base:** load, capacity e stress com o código anterior às otimizações.
4. **Otimizações, medidas uma a uma:** cache do papel, auditoria de idas por request (travada em teste), timeouts, varredura de workers, SDK enxuto e réplica com e sem.
5. **Rodada final e % de melhoria** em `docs/load/resultados.md`.

## Decidido na fase
- Limiar Alpine × Debian: troca a base se a Debian for mais de 15% melhor em RPS no teto do stress ou em p95 do load (ADR 0039).
- Metas, modelo de uso e carga de projeto de 100 req/s (ADR 0041).
- 24 workers por padrão; cortes de query da auditoria ficam de fora; réplica sem binlog próprio (ADR 0042).

## Critérios automáticos
- Smoke, load (metas do ADR 0041) e concorrência passam nos thresholds.
- Teste de idas por request trava queries e comandos Redis por rota.

## Validação manual (usuário)
1. `make load-seed` e rodar cada cenário pelo Makefile (`load-smoke`, `load-concurrency`, `load-load`, `load-capacity`, `load-stress`), e depois `make load-pipeline`.
2. Com `make obs-measure`, ver no Grafana o dashboard "k6 Prometheus" e, no "Travel Request Service", a linha "Containers" (CPU e memória por serviço) durante o load.
3. Com `make obs`, rodar um load e ver a linha "Metas" (leitura, escrita, erros, notificação ponta a ponta), o painel "Replicação" e, no Jaeger, um trace de aprovação só com spans HTTP, SQL, publish e consume.
4. Conferir em `docs/load/resultados.md` a linha de base, a atribuição de cada otimização, a rodada final com as % de melhoria e o custo do SDK.

## Aprovação
- [x] Aprovado pelo usuário em: 2026-11-11
