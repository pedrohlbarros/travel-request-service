# 0032 — Fluxo explícito e formato de chamadas

- **Status:** Aceito
- **Data:** 2026-11-10
- **Complementa:** [0026](0026-codigo-semantico.md)

## Contexto
Na revisão da fase 02 apareceram trechos difíceis de ler: `throw` escondido no fim de `??`, chamadas dentro de chamadas dentro de chamadas, cadeias e argumentos correndo para a direita da tela, e asserts misturados com código nos testes.

## Decisão
1. **Sem `throw` em expressão:** nada de `?? throw` nem `cond ? x : throw`. O caso vira `if (...) { throw ...; }`, invertendo a lógica quando isso deixar o nome mais claro. **Ternário aninhado é proibido.** O ternário simples de valor (`$isReady ? 'ok' : 'fail'`) continua permitido.
2. **No máximo dois níveis de chamada:** `f(g(x))` pode; `f(g(h(x)))` não. O valor de dentro ganha uma variável com nome. Encadeamento não conta como nível (`$a->b()->c()` é um nível).
3. **Argumentos nomeados em chamadas ao nosso código** (classes em `Domain\`, `Application\`, `Infrastructure\`, `Interfaces\`, `Tests\`, `Tools\`), mesmo com um argumento só, **e no schema builder das migrations** (`Illuminate\Database\Schema\`, só métodos reais), onde valores soltos como `16, true` não dizem nada. O resto do framework, as bibliotecas, o PHPUnit e o PHP ficam posicionais: os nomes de parâmetro deles não são nossos para manter estáveis, e métodos repassados por `__call` (como os modificadores fluentes `->nullable()`) quebram com argumentos nomeados. Spread e variadic ficam posicionais (exigência do PHP).
4. **Layout dos argumentos:** com **4 ou mais argumentos**, ou com 2 ou mais que **passariam de 120 colunas** numa linha, vai um argumento por linha. Fora isso, a chamada **fica numa linha só**, para a mesma chamada nunca aparecer de dois jeitos. Exceções: um argumento único nunca é quebrado para baixo, e um argumento que já ocupa várias linhas sozinho (closure, array multilinha) libera a chamada da linha única.
   ```php
   $table->binary(column: 'id', length: 16, fixed: true)
       ->primary();
   ```
5. **Duas ou mais chamadas encadeadas: uma por linha.** A primeira fica na linha do alvo; cada `->` seguinte começa uma linha. Uma chamada só numa variável ou propriedade (`$this->users->get(id: $id)`) fica numa linha.
   ```php
   $firstResponse = Http::timeout(self::TIMEOUT_SECONDS)
       ->withHeaders(['X-Request-Id' => $clientRequestId])
       ->get(self::BASE_URL . '/health/live');
   ```
6. **Testes:** linha em branco entre o código e os asserts, e os asserts agrupados num bloco no fim. Valores que os asserts usam são lidos antes do bloco.

## Verificação
- Regras PHPStan próprias em `tools/PHPStan`, testadas na suíte `tools`: `NoThrowInConditionalExpressionRule` (1), `MaxCallNestingRule` (2), `NamedArgumentsRule` (3), `ArgumentLayoutRule` (4, mede a largura pela posição real no arquivo) e `OneChainedCallPerLineRule` (5). Quebram o `make stan`, o pre-commit e o CI.
- Pint com `method_chaining_indentation` para indentar o `->` quebrado; o preset PER já põe um argumento por linha quando a chamada é multilinha.
- O item 6 fica para revisão.

## Consequências
- O código fica mais vertical e mais longo; em troca, cada linha diz uma coisa só.
- Os arquivos de config do Laravel também seguem as regras (variáveis antes do `return [`).
