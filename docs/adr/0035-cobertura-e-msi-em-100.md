# 0035 — Cobertura e MSI em 100%

- **Status:** Aceito
- **Data:** 2026-11-10
- **Substitui:** do [0018](0018-padrao-de-testes.md), as metas de MSI ≥ 90%, Covered MSI ≥ 95% e cobertura de linhas ≥ 85%

## Contexto
Ao fim da fase 03, a suíte estava em 98,5% de cobertura de linhas e 99% de Covered MSI. Faltavam um mutante e 26 linhas. A maior parte eram ramos testáveis que ainda não tinham teste. O resto eram ramos que não podem acontecer:
- o mutante `ArrayOneItem` em `TravelRequest::releaseEvents()`, porque hoje nenhum fluxo grava dois eventos no mesmo agregado;
- um `if`/`throw` que só existia para estreitar o tipo do token devolvido pelo parser do `lcobucci/jwt`;
- os `throw` de falha do OpenSSL, do `mkdir` e da escrita no comando que gera as chaves. Essas funções emitem um warning ao falhar, e o handler de erros do Laravel o converte em `ErrorException` antes que o nosso `throw` rode.

Com as metas antigas, uma regressão de até 15 pontos de cobertura passava no CI.

## Decisão
### Metas (quebram o build)
- **Infection** sobre `src/Domain` e `src/Application`: **MSI = 100%** e **Covered MSI = 100%**.
- **Cobertura de linhas global = 100%**.

### Como os ramos que não podem acontecer ficam
- **Eventos de agregado:** a gravação e a liberação ficam no trait `Domain\Shared\Entity\RecordsDomainEvents`, testado com um agregado de teste que grava mais de um evento. O comportamento fica coberto independentemente das regras atuais do pedido, e o próximo agregado reaproveita o trait.
- **Ramo que só estreita tipo:** vira `\assert(...)`, como o código já fazia em outros pontos.
- **Falha de função do PHP que emite warning:** sem `throw` próprio; a `ErrorException` do framework sobe com a mensagem da função. Os casos que dá para provocar (diretório que não pode ser criado, arquivo que não pode ser escrito) têm teste.
- Nenhuma linha recebe `@codeCoverageIgnore` e nenhum mutante é ignorado no Infection.

## Consequências
- Todo código novo chega com teste de todos os ramos; um ramo que não dá para provocar precisa ser repensado, não excluído.
- Uma falha silenciosa do OpenSSL, sem warning e com assertions desligadas, aparece como `TypeError` na chamada seguinte, e não como uma mensagem nossa.
