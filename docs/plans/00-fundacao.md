# Fase 00 — Fundação

- **Status:** Proposto
- **ADRs:** 0001, 0002, 0009, 0011, 0013, 0014, 0018, 0019

## Objetivo
Esqueleto executável: o projeto sobe via Docker, toda a cadeia de qualidade roda, e a estrutura hexagonal existe — sem domínio ainda.

## Escopo
- Laravel (última estável) em PHP 8.5 com Octane + Swoole; Dockerfile multi-stage (dev/test e runtime).
- `compose.yaml` com o perfil padrão: app, MySQL (database de teste incluída), Redis, RabbitMQ (com management), Mailpit. Reverb e workers entram nas fases que os usam.
- **Todas as portas de host via `.env`**, defaults de cada serviço, documentadas no `.env.example`.
- Estrutura `src/{Domain,Application,Infrastructure,Interfaces}` com autoload PSR-4; `app/` só bootstrap.
- `tests/{Domain,Application,Infrastructure,Interfaces,EndToEnd,Support}` e suítes PHPUnit `unit`, `integration`, `feature`, `e2e`.
- Pint (preset PER + regras extras), PHPStan + Larastan max sem baseline (+ strict/deprecation rules), Deptrac com camadas e contexts, Infection configurado (metas do ADR 0018).
- `/health/live` e `/health/ready` (checa MySQL, Redis, RabbitMQ).
- Pontos de extensão para OTel previstos (sem a stack `obs` ainda), para não exigir retrofit na fase 06.
- **Makefile catálogo** (ADR 0019) com `make help` por seções; nesta fase: ambiente (up, down, logs, ps, **lista de IP/porta/URL de cada serviço**), setup, qualidade, testes, infection. Alvos de usuários/carga/perfis entram como stub ou nas fases seguintes.
- CaptainHook (pre-commit: Pint + PHPStan; commit-msg: Conventional Commits).
- GitHub Actions com o mesmo pipeline do `make qa`.

## Fora de escopo
Qualquer regra de domínio, auth, mensageria, observabilidade.

## A decidir na fase
- Nomes finais das variáveis de porta e dos alvos do Makefile (proposta apresentada antes de implementar).
- Verificação concreta de suporte do Swoole e da extensão OTel ao PHP 8.5 — se houver problema, levar opções.

## Critérios automáticos
- `make up` sobe tudo saudável; `make qa` verde (Pint, PHPStan, Deptrac, suítes, Infection sem código ainda).
- CI verde no GitHub Actions.
- Teste de feature para `/health/ready`.

## Validação manual (usuário)
1. Copiar `.env.example` → `.env`, **trocar a porta do MySQL** (ex.: 3307) e rodar `make up` — deve subir sem conflito.
2. `make help` — conferir seções e alvos.
3. Alvo de listagem de serviços — conferir IP/porta/URL de cada um.
4. Abrir `/health/ready`, UI do RabbitMQ e do Mailpit nas URLs listadas.
5. `make qa` local.
6. Fazer um commit de teste: hook roda Pint + PHPStan e rejeita mensagem fora de Conventional Commits.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
