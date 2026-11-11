# 0024 — Módulo Shared e regras de dependência do Deptrac

- **Status:** Aceito (wiring em `app/` substituído por [0025](0025-providers-por-contexto-sem-app.md); pastas do exemplo reorganizadas por tipo no [0029](0029-organizacao-por-tipo-e-use-cases.md); leitura da tabela de outro context na Infrastructure refinada por [0034](0034-relacionamento-pedido-usuario.md))
- **Data:** 2026-11-09
- **Refina:** [0002](0002-arquitetura-ddd-hexagonal.md)

## Contexto
Health checks, middleware de `request_id`/`traceparent` e a base de erros RFC 9457 não pertencem a nenhum dos três contexts. O ADR 0002 também não diz se os adapters (`Interfaces`, `Infrastructure`) podem depender um do outro.

## Decisão
### Shared por camada
Código transversal vive em `src/<Camada>/Shared/...` (`Domain\Shared`, `Application\Shared`, `Infrastructure\Shared`, `Interfaces\Http\Shared`). Qualquer context pode usar `Shared`, mas `Shared` não usa nenhum context.

Exemplo (fase 00): `Application\Shared\Health` (port `HealthCheck` + query `CheckReadiness`), `Infrastructure\Shared\Health` (MySQL, Redis, RabbitMQ), `Interfaces\Http\Shared` (controllers de health e middleware).

### Camadas (hexagonal estrito)
```
Domain         -> (nada)
Application    -> Domain
Infrastructure -> Application, Domain
Interfaces     -> Application, Domain
App (bootstrap)-> tudo
```
`Interfaces` **não** depende de `Infrastructure`: adapters só se conectam pelas ports, com binding nos ServiceProviders em `app/`.

### Contexts
Deptrac declara `TravelRequest`, `Identity`, `Notification` e `Shared`. Entre contexts, a comunicação é por eventos (ADR 0007). Dependências diretas entre contexts não são permitidas, exceto para `Shared`.

### Helpers globais do framework
O Deptrac só enxerga uma chamada de função quando ela é totalmente qualificada (`\now()`) ou importada (`use function now;`). Uma chamada sem barra dentro de um namespace (`now()`) passa sem violação, e o Pint não resolve isso: ele só importa o que já estava qualificado.

A proibição de helpers (`now()`, `config()`, `app()`...) em `Domain`/`Application` fica numa **regra PHPStan própria** (`tools/PHPStan/NoFrameworkFunctionsInCoreRule`). Ela resolve a função de verdade e barra qualquer função definida em `vendor/laravel` chamada de `src/Domain` ou `src/Application`. Fica no repo, sem dependência nova, e tem testes na suíte `tools`. O Deptrac continua responsável por classes, camadas e contexts.

## Consequências
- Um Resource em `Interfaces` não lê um model Eloquent direto: recebe DTO/read model vindo de `Application`.
- `app/Providers` concentra o wiring entre ports e adapters.
