# 0041 — Metas de desempenho e carga de projeto

- **Status:** Aceito
- **Data:** 2026-11-11
- **Substitui:** os thresholds do load no [0018](0018-padrao-de-testes.md) (p95 < 200 ms, erros < 1%) e os do load e do stress no [0039](0039-detalhes-dos-testes-de-carga.md)
- **Refina:** [0039](0039-detalhes-dos-testes-de-carga.md)

## Contexto
O threshold de p95 < 200 ms e os 50 VUs do ADR 0018 vieram de exemplos comuns do k6, sem medição nem referência por trás. A primeira rodada da fase 07 reprovou as escritas por pouco (p95 de 255–284 ms) com 50 VUs, cerca de 500 req/s, numa máquina que divide as CPUs entre a API, os bancos, os consumidores e o próprio k6. Antes de otimizar, a régua precisa de base: o que o mercado aceita, o uso que o serviço deve ter de fato e onde cada número é medido.

## Referências
- **Percepção:** até 0,1 s parece instantâneo, até 1 s mantém o fluxo do usuário e 10 s é o limite da atenção ([Nielsen Norman Group](https://www.nngroup.com/articles/response-times-3-important-limits/)).
- **Resposta de interface:** cerca de 100 ms entre a ação e a resposta, com 50 ms de processamento ([Google RAIL](https://web.dev/articles/rail)).
- **SLOs de exemplo do Google:**
  - API: 90% < 400 ms e 99% < 850 ms;
  - servidor HTTP: 90% < 200 ms e 99% < 1 s.

  O próprio documento avisa que os números não vêm de evidência ([SRE Workbook, exemplo de documento de SLO](https://sre.google/workbook/slo-document/)). A recomendação é expressar a latência em percentis e escolher a meta pela experiência do usuário ([SRE Book, capítulo de SLOs](https://sre.google/sre-book/service-level-objectives/)).
- **Satisfação:** o Apdex usa um limiar T escolhido por quem mede. Até T o usuário está satisfeito, até 4T tolera ([New Relic](https://docs.newrelic.com/docs/apm/new-relic-apm/apdex/apdex-measure-user-satisfaction/)).
- **Percentis:** guias de SLO usam o p95 como meta principal e o p99 para caminhos críticos.
- **Folga de CPU:** guias de capacity planning miram 60–70% no pico, para absorver rajadas e a perda de uma instância.
- **Mensageria:** as metas ponta a ponta saem do negócio. Os exemplos ficam em p95 < 1 s para eventos de tempo real.
- **Réplica:** os alertas comuns avisam em cerca de 10 s, ficam críticos entre 30 e 60 s e tratam a replicação parada à parte.

## Decisão
### Modelo de uso
Estimativa de uso para dar ordem de grandeza à carga, com premissas ajustáveis:
- 20% dos funcionários viajam, 6 viagens por ano cada, em 250 dias úteis;
- cerca de 18 operações por pedido: 1 criação, 0,3 edição, 1 decisão, cerca de 10 leituras do solicitante, 5 do admin e 1 listagem de notificações. As notificações chegam por broadcast, sem polling;
- 20% do dia concentrado na hora de pico, e o segundo mais cheio dessa hora com 3× a média dela.

| Cenário | Pico estimado |
|---|---|
| Empresa de 10 mil funcionários | ~0,15 req/s |
| Empresa de 100 mil funcionários | ~1,5 req/s |
| Evento de massa: 2 mil pedidos criados na mesma hora | ~10 req/s |

### Carga de projeto: 100 req/s
É cerca de 60× o pico de uma empresa de 100 mil funcionários e 10× o evento de massa. As metas valem nessa carga. Os 50 VUs passam a medir capacidade, e o número que interessa é a razão entre o teto e a carga de projeto.

### Metas

| Ponto | Meta | Onde se mede |
|---|---|---|
| Latência de leitura (listar, detalhe) | p95 ≤ 150 ms, p99 ≤ 300 ms | load (k6), por operação |
| Latência de escrita (criar, editar, aprovar, cancelar) | p95 ≤ 250 ms, p99 ≤ 500 ms | load (k6), por operação |
| Erros | < 0,1% | load e capacity (k6) |
| CPU | ≤ 70% na carga de projeto, por serviço sobre os núcleos dele | load (cAdvisor) |
| Saturação | ≥ 100 VUs dentro da meta de leitura e de erros | stress |
| Pipeline decisão → notificação | vazão ≥ 1,5× o pico de decisões, fila sem crescer durante o load, p95 ponta a ponta ≤ 5 s | load |
| Defasagem da réplica | < 1 s, com alerta de replicação parada | load (gauge) |
| Custo do SDK OTel | ≤ 10% de perda de vazão | capacity com e sem o SDK |

- A mistura de operações continua 70% leitura e 30% escrita (ADR 0039). É mais pesada em escrita que o modelo de uso, que dá cerca de 85/15. Assim a medição fica conservadora e comparável com a primeira rodada.
- O commit durável (`sync_binlog=1`, `innodb_flush_log_at_trx_commit=1`) continua sendo o oficial. O relaxado entra só como comparação.

### Cenários
- **Load:** taxa constante (`constant-arrival-rate`) que chega a cerca de 100 req/s por 5 min. Os thresholds são as metas de latência por operação, os erros e a ausência de iterações descartadas. A taxa de iterações vem do `.env` do k6, porque cada iteração faz em média mais de uma request.
- **Capacity:** os 50 VUs por 5 min de antes. Só erros e checks são thresholds. Vazão e latência ficam registradas para comparar as rodadas.
- **Stress:** os mesmos estágios. O teto passa a ser o último estágio com p95 de leitura ≤ 150 ms e erros < 0,1%.
- **Smoke e concorrência:** sem mudança.

## Consequências
- O load passa com folga na carga de projeto se a latência sob 100 req/s for boa. A capacidade fica visível à parte, sem virar meta de latência.
- Os resultados da primeira rodada continuam registrados, mas não se comparam diretamente: a melhoria é medida contra uma nova linha de base com estes cenários.
- O modelo de uso é uma estimativa. Se os dados reais divergirem, a carga de projeto muda por um ADR novo.
