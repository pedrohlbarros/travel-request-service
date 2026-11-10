# travel-request-service

Microsserviço em Laravel para gestão de pedidos de viagem corporativa. É um teste técnico; o enunciado original está em [docs/desafio.md](docs/desafio.md).

## Como trabalhar neste repo

- **Claude sugere, o usuário decide.** Nenhuma decisão de arquitetura, regra de negócio, padrão ou dependência é tomada sem aval explícito. Ao encontrar algo não coberto pelos ADRs, pare e pergunte com opções e uma recomendação.
- **Cada fase em branch e pull request:** a fase começa numa branch `phase/NN-<nome-em-ingles>` criada a partir da `main` atualizada (ex.: `phase/01-travel-request-domain`). Nada é commitado durante a fase. Correção fora de fase usa branch `fix/<descricao-em-ingles>` com o mesmo fluxo de tabela, commits e PR.
- **Commits só após aprovação, feitos pelo Claude:** depois da aprovação manual da fase, o Claude propõe uma tabela de commits (mensagem + arquivos/conteúdo de cada um). O usuário confirma ou altera, e só então o Claude faz os commits um a um, na ordem da tabela. Commits pequenos e coesos, em Conventional Commits (inglês), passando pelos hooks do CaptainHook (nunca `--no-verify`).
- **Push e PR:** o Claude dá push da branch e abre o PR com `gh pr create` contra a `main`. Título em Conventional Commits (inglês); corpo em português com resumo, ADRs e roteiro de validação. Depois acompanha o CI (`gh pr checks`) e reporta o resultado. **O merge é sempre do usuário.** Claude nunca faz merge nem push direto na `main`.
- **Sem referência a documentação fora de `docs/`:** código, Dockerfile, compose, configs, `.env.example` e testes nunca citam ADR, plano, fase ou `docs/` em comentários. Comentário só explica código não óbvio; contexto e justificativa ficam em `docs/`.
- **Sem atribuição do Claude:** commits e PRs nunca levam `Co-Authored-By` do Claude nem "Generated with Claude Code".
- **Uma fase por vez.** Implementar seguindo o plano da fase em `docs/plans/`, com testes.
- **Toda fase termina com validação e aprovação manual do usuário.** Ao final: rodar `make qa`, apresentar o roteiro de validação manual do plano (comandos e o que observar) e **parar**. A próxima fase só começa depois do "aprovado" explícito. Registrar a aprovação no plano (status + data).
- **Decisões registradas viram ADR** em `docs/adr/`. Mudou uma decisão? ADR novo que substitui o antigo.
- **Idioma:** código (classes, métodos, rotas, enums, colunas) em inglês; documentação (README, CLAUDE.md, ADRs, planos) em português.
- **README.md enxuto:** só o que o enunciado pede + prints. Detalhes vão para `docs/` (ADR 0013).

## Decisões vigentes (resumo — fonte da verdade: `docs/adr/`)

| Tema | Decisão | ADR |
|---|---|---|
| Runtime | PHP 8.5, Laravel (última estável), Octane + Swoole, MySQL, REST | [0001](docs/adr/0001-stack-e-runtime.md) |
| Arquitetura | DDD + Hexagonal; contexts TravelRequest, Identity, Notification; `src/{Domain,Application,Infrastructure,Interfaces}/<Context>`; sem `app/`: providers por context/camada ([0025](docs/adr/0025-providers-por-contexto-sem-app.md)) | [0002](docs/adr/0002-arquitetura-ddd-hexagonal.md) |
| Regras | Status `requested`/`approved`/`cancelled`; só admin muda status; `requested → approved/cancelled`; aprovado não cancela; admin pode aprovar o próprio; admin vê todos; dono edita dados só em `requested` | [0003](docs/adr/0003-regras-de-negocio-do-pedido.md) |
| Autorização | Enum `role` (`user`/`admin`) + Policies | [0004](docs/adr/0004-autorizacao-role-e-policies.md) |
| Autenticação | `lcobucci/jwt`, RS256 + JWKS (`kid`), claims `iss/aud/nbf` sem role/PII, access 15 min, refresh 7 dias opaco rotativo (hash + família no MySQL), chaves geradas no setup fora do git; register público cria `user`, admin só por seeder/comando | [0005](docs/adr/0005-autenticacao-jwt.md) |
| Concorrência | `version` + `ETag`/`If-Match`: sem header 428, divergente 412, regra de estado 409, pedido alheio 404, ação admin por user 403 | [0006](docs/adr/0006-concorrencia-versionamento-otimista.md) |
| Mensageria | Outbox + relay polling (`SKIP LOCKED`, confirms); exchange topic `travel_request.events`, keys `travel_request.<evento>.v1`; CloudEvents gordo + `traceparent`; filas quorum; inbox table; retry com backoff + DLQ | [0007](docs/adr/0007-mensageria-rabbitmq-e-outbox.md) |
| Redis | Fila de jobs, cache (detalhe por ID + listas por geração), blacklist de JWT, rate limit **só em auth** (proposital, por causa da carga) | [0008](docs/adr/0008-papeis-do-redis.md) |
| Docker | Sem perfil: app, MySQL, Redis, RabbitMQ, Mailpit, Reverb e workers. Perfis `obs`, `load` (k6), `web` (frontend opcional) | [0009](docs/adr/0009-infra-docker-e-perfis.md) |
| Observabilidade | OTel + Jaeger + Prometheus + Grafana; frontend opcional para trace ponta a ponta | [0010](docs/adr/0010-observabilidade.md) |
| Qualidade | PHPUnit, Infection, k6 | [0011](docs/adr/0011-qualidade-e-testes.md) |
| Destino | String no pedido | [0012](docs/adr/0012-destino-como-string.md) |
| Notificação | Mail (Mailpit), database (in-app), broadcast (Reverb) | [0020](docs/adr/0020-canais-de-notificacao.md) |
| Imagens | App `php:8.5-cli-alpine` + Swoole 6.2.x; MySQL 8.4, Redis 8.x, RabbitMQ 4.3-management, Mailpit v1.33; ressalva musl medida na fase 07 | [0022](docs/adr/0022-imagem-base-e-versoes.md) |
| Fundação | phpredis, `php-amqplib`, PCOV, CaptainHook (docker + `ramsey/conventional-commits`), cache/queue redis, session array, skeleton enxuto | [0023](docs/adr/0023-dependencias-e-drivers-da-fundacao.md) |
| Shared/Deptrac | Transversal em `src/<Camada>/Shared`; `Interfaces` não depende de `Infrastructure`; contexts só se falam por eventos | [0024](docs/adr/0024-modulo-shared-e-regras-do-deptrac.md) |
| CI | GitHub Actions builda a imagem e roda `make up` + `make qa` via compose (sem `services:`) | [0021](docs/adr/0021-ci-com-docker-compose.md) |
| Docs | Planos + ADRs + diagramas; README enxuto | [0013](docs/adr/0013-documentacao.md) |
| Padrões | Código, Application (CQRS leve), API, IDs, testes, automação | 0014–0019 |

## Portas e ambiente

Toda porta publicada no host é configurável via `.env` (documentada no `.env.example`), com o default de cada serviço (MySQL 3306, Redis 6379, etc.). Quem roda o teste pode já ter um MySQL local: basta trocar a porta no `.env` antes do `make up`. Nunca fixar porta de host no `compose.yaml`.

## Cuidados com Octane/Swoole

O worker é reaproveitado entre requests: nada de estado por request em singletons, propriedades estáticas ou serviços com estado mutável.

## Padrões de código ([0014](docs/adr/0014-padrao-de-codigo.md), [0015](docs/adr/0015-camada-application-cqrs.md), [0017](docs/adr/0017-identificadores-uuidv7.md))

- `declare(strict_types=1)` em todo arquivo; tipos nativos em tudo; sem `mixed` fora de adapters.
- Classes `final` por padrão; VOs, DTOs, Commands, Queries e Events como `readonly class`.
- `src/Domain` e `src/Application` **não importam** `Illuminate\*`, Facades nem helpers globais (`now()`, `auth()`, `config()`...). Dependência externa entra por port + injeção.
- Casos de uso: Command/Query `readonly` + um Handler cada, chamados direto pelo controller (sem bus).
- IDs UUIDv7 gerados no domínio, `BINARY(16)` no MySQL.
- Docblock só quando o tipo não diz: generics/shapes do PHPStan, `@throws` de exceção de domínio, porquê de regra não óbvia.
- Estilo: Pint com preset PER. Análise: PHPStan + Larastan nível max, sem baseline; complexidade cognitiva função ≤ 9, classe ≤ 40 ([0027](docs/adr/0027-complexidade-cognitiva.md)). Fronteiras: Deptrac.
- Código e comentários em inglês.
- **Código semântico ([0026](docs/adr/0026-codigo-semantico.md)):** sem valor mágico inline (regex/limites em constante com nome), API com nome antes de regex à mão, grupos de regex nomeados, sem nomes curtos/genéricos (`$tmp`, `$data`, `$a`; exceções `$i`, `$id`, `$ip`), booleanos como pergunta, testes com entradas nomeadas e arrange/act/assert. Comentário só explica o porquê.

## Padrões de API ([0016](docs/adr/0016-convencoes-da-api.md))

- Rotas em `/api/v1`; mudança de status por ação explícita (`POST .../{id}/approve`, `.../cancel`).
- Listagem com paginação por cursor; JSON `snake_case`; datas `YYYY-MM-DD`, timestamps ISO 8601 UTC.
- Erros em RFC 9457 (`application/problem+json`) com `trace_id`/`request_id`; `type` como URL relativa documentada (`/problems/...`).
- Rotas e filtros fechados no ADR 0016 — não inventar rota nova sem aval.
- OpenAPI gerado pelo Scramble: FormRequests e Resources bem tipados são a documentação.

## Padrões de teste ([0018](docs/adr/0018-padrao-de-testes.md))

- `tests/` espelha `src/`: `Domain`, `Application` (suíte unit), `Infrastructure` (integration), `Interfaces/Http` (feature), mais `EndToEnd/` e `Support/`.
- Nome: `test_` + PascalCase (`test_CannotCancelApprovedTravelRequest`).
- MySQL real no Docker, sem SQLite. Fakes in-memory das ports em `tests/Support`; verificar estado, não chamadas.
- Metas que quebram o build: Infection em Domain+Application com MSI ≥ 90% e Covered MSI ≥ 95%; cobertura global ≥ 85%.
- Toda mudança de comportamento vem com teste.

## Comandos ([0019](docs/adr/0019-automacao-de-qualidade.md))

Tudo pelo `Makefile`, rodando dentro do container. O Makefile é o **catálogo completo** de ações: subir/derrubar, `make help` (lista os alvos por seção), listar IP/porta/URL de cada serviço, setup, criar usuários/admin, todas as suítes de teste, Infection, cenários de carga, perfis `obs`/`load`/`web`. Toda ação nova útil para validar ou testar ganha um alvo (`make up`, `test`, `lint`, `stan`, `arch`, `infection`, `load`, `qa`). Antes de declarar uma fase pronta, `make qa` precisa passar. CaptainHook: Pint + PHPStan no pre-commit; Conventional Commits (em inglês) no commit-msg. CI no GitHub Actions via Docker Compose, rodando o mesmo `make qa` (ADR 0021).

## Em aberto

- Nada além do que cada plano lista em "A decidir na fase".

## Para registrar na entrega (README ou doc linkado)

- Ambiguidades do enunciado: admin pode aprovar o próprio pedido; admin vê todos os pedidos (ADR 0003).
- API autenticada sem rate limit de propósito, por causa dos testes de carga (ADR 0008).
- Evento gordo carrega PII do solicitante no broker (ADR 0007).
- Evolução possível: IdP externo (Keycloak) trocando só o adapter de token (ADR 0005).
