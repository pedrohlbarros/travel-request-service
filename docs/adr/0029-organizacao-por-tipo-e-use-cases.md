# 0029 — Organização por tipo e casos de uso com Input/Output

- **Status:** Aceito
- **Data:** 2026-11-09
- **Substitui:** a nomenclatura Command/Query + Handler do [0015](0015-camada-application-cqrs.md)

## Contexto
Na revisão da fase 01, os arquivos de `Application/TravelRequest` (Commands, Handlers, View) estavam todos numa pasta só, e o nome "Handler" não diz que a classe é um caso de uso. O projeto precisa de um padrão único de pastas em todas as camadas.

## Decisão
### Pastas por tipo, em todas as camadas
Dentro de `src/<Camada>/<Context>/` (e `Shared`), as classes ficam em subpastas pelo tipo:

| Camada | Subpastas |
|---|---|
| Domain | `Entity/`, `ValueObject/`, `Enum/`, `Event/`, `Exception/`, `Repository/` |
| Application | `UseCase/`, `Input/`, `Output/`, `Port/` |
| Interfaces (HTTP) | `Controller/`, `Middleware/` (e, nas próximas fases, `Request/`, `Resource/`, `Policy/`...) |
| Infrastructure | por tipo de adapter (ex.: `Health/`, `Messaging/`, `Persistence/`) |

`tests/` espelha a mesma estrutura; `tests/Support` separa `Port/` (fakes das ports), `Repository/` (repositórios in-memory) e `Fixture/` (montagem de cenários).

### Caso de uso
- Classe `<Ação>UseCase` com um único método público `execute()`, chamado direto pelo controller (sem bus).
- Entrada em DTO `<Ação>Input` (`readonly class`). **Exceção:** caso de uso com um único parâmetro o recebe direto (ex.: `execute(string $travelRequestId)`); sem parâmetros, `execute()`.
- Saída em DTO `*Output` (`readonly class`). Casos de uso que devolvem a mesma forma compartilham o Output: todos os casos do pedido devolvem `TravelRequestOutput` com o estado após a operação, para a API responder com corpo e novo `ETag` sem nova leitura.
- Ports da Application ficam em `Application/<Context>/Port/` (ou `Application/Shared/Port/`). A interface do repositório continua no Domain (`Repository/`).

## Consequências
- `CheckReadinessHandler` vira `CheckReadinessUseCase::execute(): ReadinessOutput`, sem Input (não tem parâmetro).
- Um caso de uso fica espalhado em três pastas (`UseCase/`, `Input/`, `Output/`), em troca de um padrão igual em todo o projeto.
- O lado de leitura ainda pode usar read model/query service próprio (0015), exposto como `UseCase` + `Output`.
