# 0027 — Limite de complexidade cognitiva

- **Status:** Aceito
- **Data:** 2026-11-09
- **Complementa:** [0014](0014-padrao-de-codigo.md)

## Contexto
Limites de tamanho de arquivo do mercado são permissivos (PHPMD: classe ≤ 1000 linhas; Sonar: arquivo ≤ 1000 linhas, função ≤ 150) e pegam só exagero. O que o mercado mede de fato é complexidade. A SonarQube exige servidor próprio ou serviço externo, o que não encaixa no "tudo via `make`, dentro do container" (ADR 0019).

## Decisão
- **`tomasvotruba/cognitive-complexity`** como extensão do PHPStan, com os defaults do pacote: **função ≤ 9**, **classe ≤ 40**.
- Roda no `make stan`, no pre-commit e no CI, sobre os mesmos paths do PHPStan.
- Sem limite de linhas por arquivo/método nem de parâmetros.

## Alternativas descartadas
- SonarQube Server/Cloud: infra pesada ou envio do código para fora, sem feedback local.
- Limites da Sonar via PHPStan (cognitiva 15 + regra própria de linhas/parâmetros/métodos): mais regras para manter, com limite de complexidade mais frouxo.

## Consequências
- Métodos complexos quebram o build e precisam ser divididos.
- Linha de 120 continua só como guia do PER (Pint não aplica).
