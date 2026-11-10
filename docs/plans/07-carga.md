# Fase 07 — Testes de carga

- **Status:** Proposto
- **ADRs:** 0008, 0009, 0011, 0018, 0022

## Objetivo
Medir e garantir performance e a garantia de concorrência com k6.

## Escopo
- Perfil `load` com k6; métricas via remote write para o Prometheus quando `obs` está no ar; dashboard k6 no Grafana.
- Cenários: smoke (1 VU), load (ex.: 50 VUs/5 min, p95 < 200 ms, erros < 1%), stress (rampa até quebrar), concorrência (N admins no mesmo pedido → exatamente 1 sucesso, resto 412/409).
- Dados de carga preparados por seeder/alvo dedicado.
- Makefile: um alvo por cenário.
- **Ressalva musl (ADR 0022):** rodar load e stress na imagem Alpine e numa variante Debian temporária com as mesmas extensões; comparar RPS, p95/p99, uso de memória e teto do stress. Registrar os números para a entrega.

## A decidir na fase
- Limiar de "diferença relevante" Alpine × Debian que justifica trocar a imagem base (proposta apresentada antes de medir).

## Critérios automáticos
- Smoke, load e concorrência passam nos thresholds.

## Validação manual (usuário)
1. Rodar cada cenário pelo Makefile.
2. Ver as métricas no Grafana durante o load e o stress.
3. Conferir números registrados (teto encontrado no stress) para a entrega.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
