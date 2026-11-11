# Guia de testes

Os testes rodam dentro do container do app, contra MySQL, Redis e RabbitMQ reais (sem SQLite). Antes de rodar, o ambiente precisa estar no ar (`make up`). As regras estão nos ADRs [0018](adr/0018-padrao-de-testes.md), [0019](adr/0019-automacao-de-qualidade.md) e [0035](adr/0035-cobertura-e-msi-em-100.md).

```sh
make test   # todas as suítes
make qa     # o mesmo que o CI: lint, análise estática, arquitetura, suítes com cobertura e mutation testing
```

## Suítes

A pasta `tests/` espelha `src/`, e cada camada tem a sua suíte:

| Suíte | Pasta | O que cobre | Comando |
|---|---|---|---|
| unit | `tests/Domain`, `tests/Application` | Regras do domínio e casos de uso, com fakes em memória das ports | `make test-unit` |
| integration | `tests/Infrastructure` | Repositórios, cache, eventos pendentes, publicador, consumidor, tokens e health checks contra os serviços reais | `make test-integration` |
| feature | `tests/Interfaces` | Endpoints HTTP: validação, autorização, Problem Details, ETag e If-Match | `make test-feature` |
| e2e | `tests/EndToEnd` | O servidor Octane no ar: decisão → relay → RabbitMQ → consumidor → notificação e e-mail | `make test-e2e` |
| tools | `tests/Tools` | As regras próprias do PHPStan | `make test-tools` |

`tests/Support` tem o que é compartilhado: fakes em memória das ports (repositórios, relógio, gerador de ids, publicador de eventos), *mothers* de fixtures e traits para autenticar, chamar a API, usar as filas e capturar telemetria.

## Convenções

- **Nome:** `test_` + a regra em PascalCase, por exemplo `test_CannotCancelApprovedTravelRequest`.
- **Estrutura:** arrange / act / assert, com uma linha em branco antes do bloco de asserts.
- **Entradas com nome:** valores em constantes ou fixtures, sem números mágicos.
- **Estado, não chamadas:** os fakes guardam estado, e o teste confere o resultado, não quais métodos foram chamados.
- **Toda mudança de comportamento vem com teste.**

## Ferramentas e régua

| Ferramenta | Uso | Régua (quebra o build) |
|---|---|---|
| PHPUnit 13 | Todas as suítes, com cobertura pelo PCOV | 100% das linhas de `src/` (`make coverage`) |
| Infection | Mutation testing em `src/Domain` e `src/Application` | MSI e Covered MSI de 100% (`make infection`) |
| PHPStan + Larastan | Análise estática de `src`, `tests`, `config`, `routes` e `tools` | Nível max, sem baseline (`make stan`) |
| Cognitive complexity | Extensão do PHPStan | Até 9 por função e 40 por classe |
| Regras próprias do PHPStan (`tools/PHPStan`) | Padrões do projeto | Sem helpers do framework no Domain e na Application; sem nomes vagos; sem `throw` em expressão; no máximo dois níveis de chamada aninhada; argumentos nomeados no nosso código; layout de argumentos e um `->` por linha |
| Deptrac | Fronteiras de arquitetura (`make arch`) | Domain não depende de nada; Application só de Domain; Infrastructure e Interfaces de Application, Domain e framework; os contexts só conhecem o Shared |
| Pint | Estilo de código, preset PER (`make lint`) | Sem diferença de estilo |
| CaptainHook | Git hooks | Pint e PHPStan no pre-commit; Conventional Commits no commit-msg |

Não se usa `@codeCoverageIgnore` nem mutante ignorado. Um ramo que só existe para estreitar tipo vira `\assert`.

## Contratos travados em teste

Além das regras de negócio, alguns comportamentos não funcionais têm teste próprio:
- **Idas por request:** quantas queries e comandos Redis cada rota faz com o cache quente (`RoundTripsPerRequestTest`).
- **Concorrência:** uma decisão concorrente perde com 412 ou 409, e a versão nunca pula.
- **Mensageria:** nenhuma entrega duplicada (registro de eventos processados), retry com as esperas configuradas e DLQ com o motivo.
- **Observabilidade:** trace pai-filho HTTP → publish → consume, métricas de negócio e de entrega.
- **Erros:** o formato do Problem Details e o catálogo de `type`.

## Testes manuais

- `make demo`: percorre o fluxo inteiro, da criação à notificação, e imprime cada passo.
- [api/requests.http](api/requests.http): coleção para o HTTP Client do PhpStorm, com o caminho feliz e os erros 403, 404, 409, 412, 422 e 428.
- Página de notificações em http://localhost:8000/docs/notifications: entre como Luffy, rode `make demo` e veja a decisão chegar ao vivo.
- E-mails no Mailpit em http://localhost:8025.

## CI

O [GitHub Actions](../.github/workflows/ci.yml) builda a imagem e roda, pelo Docker Compose, os mesmos alvos do `Makefile`:
1. `make setup`
2. `make qa`
3. `make load-seed`, `make load-smoke` e `make load-concurrency`

Os testes de carga completos ficam fora do CI e estão descritos em [testes-de-carga.md](testes-de-carga.md).
