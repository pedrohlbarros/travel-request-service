# 0045 — Meta de p99 no agregado de leitura e escrita

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0041](0041-metas-de-desempenho-e-carga-de-projeto.md)

## Contexto
O ADR 0041 aplicou as metas de p95 e de p99 a cada operação do load. A edição é só 5% da mistura: são cerca de 950 requests em 5 min, e o p99 dela é decidido pelas ~10 mais lentas. Numa rodada de avaliação, o p99 da edição deu 527 ms contra a meta de 500 e, na rodada seguinte, 478 ms. O p95 da edição ficou entre 88 e 126 ms, contra a meta de 250. A oscilação vem do tamanho da amostra (picos de commit e esperas de lock), não de uma regressão do serviço.

## Decisão
- Por operação (listar, próxima página, detalhe, criar, editar, aprovar, cancelar), o threshold do load é só o p95: leitura ≤ 150 ms e escrita ≤ 250 ms.
- O p99 é exigido no agregado: todas as leituras ≤ 300 ms e todas as escritas ≤ 500 ms. Com milhares de requests em cada grupo, o percentil fica estável.
- O resumo do k6 continua mostrando o p99 de cada operação, para acompanhar a cauda sem que ela reprove a rodada.

## Consequências
- Uma operação rara com a cauda ruim não reprova o load sozinha, mas aparece no resumo e pesa no p99 agregado.
- As metas de valor do ADR 0041 não mudam. Muda só onde o p99 é cobrado.
