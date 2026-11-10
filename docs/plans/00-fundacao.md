# Fase 00 — Fundação

- **Status:** Concluído (2026-11-09)
- **ADRs:** 0001, 0002, 0009, 0011, 0013, 0014, 0018, 0019, 0021, 0022, 0023, 0024, 0025, 0026, 0027

## Objetivo
Esqueleto executável: o projeto sobe via Docker, toda a cadeia de qualidade roda, e a estrutura hexagonal existe — sem domínio ainda.

## Escopo
- Laravel 13 (última estável) em PHP 8.5 com Octane + Swoole 6.2.x; Dockerfile multi-stage (dev/test e runtime) sobre `php:8.5-cli-alpine` (ADR 0022).
- `compose.yaml` com o perfil padrão: app, MySQL 8.4, Redis 8.x, RabbitMQ 4.3-management, Mailpit v1.33 (ADR 0022). Reverb e workers entram nas fases que os usam: o ADR 0009 continua valendo e é atendido incrementalmente.
- Database de teste criada por init script do MySQL.
- `UID`/`GID` como build args, para arquivos gerados no container (vendor, cache) não ficarem com dono root no host.
- **Todas as portas de host via `.env`**, defaults de cada serviço, documentadas no `.env.example`: `FORWARD_APP_PORT=8000`, `FORWARD_DB_PORT=3306`, `FORWARD_REDIS_PORT=6379`, `FORWARD_RABBITMQ_PORT=5672`, `FORWARD_RABBITMQ_UI_PORT=15672`, `FORWARD_MAILPIT_SMTP_PORT=1025`, `FORWARD_MAILPIT_UI_PORT=8025`.
- Estrutura `src/{Domain,Application,Infrastructure,Interfaces}` com autoload PSR-4; sem `app/`, providers por context/camada (ADR 0025).
- `tests/{Domain,Application,Infrastructure,Interfaces,EndToEnd,Support}` e suítes PHPUnit `unit`, `integration`, `feature`, `e2e`, mais `tools` (testes do tooling do repo em `tests/Tools`).
- Complexidade cognitiva no PHPStan (`tomasvotruba/cognitive-complexity`, função ≤ 9, classe ≤ 40; ADR 0027).
- Regras PHPStan próprias: helpers do framework proibidos em `Domain`/`Application` (ADR 0024) e nomes curtos/genéricos (ADR 0026).
- Pint (preset PER + regras extras), PHPStan + Larastan max sem baseline (+ strict/deprecation rules), Deptrac com camadas e contexts, Infection configurado (metas do ADR 0018).
- Driver de cobertura **PCOV**. Meta de cobertura global (≥ 85%) verificada por script que lê o clover e quebra o build.
- Infection roda com `--ignore-msi-with-no-mutations` **só nesta fase** (ainda não há código em Domain/Application); a flag sai na fase 01.
- `/health/live` e `/health/ready` (checa MySQL, Redis, RabbitMQ). Cliente AMQP: **`php-amqplib`** (PHP puro, com publisher confirms, reaproveitado no relay da fase 04).
- Pontos de extensão para OTel, sem dependência nova: middleware que gera/propaga `request_id` e lê `traceparent`; variáveis `OTEL_*` no `.env.example` com `OTEL_SDK_DISABLED=true`. Extensão e SDK entram na fase 06.
- **Makefile catálogo** (ADR 0019) com `make help` por seções:
  - **Ambiente:** `build`, `up`, `down`, `restart`, `logs`, `ps`, `urls` (IP/porta/URL de cada serviço)
  - **Setup:** `setup`, `shell`, `composer`
  - **Qualidade:** `lint`, `lint-fix`, `stan`, `arch`, `qa`
  - **Testes:** `test`, `test-unit`, `test-integration`, `test-feature`, `test-tools`, `test-e2e`, `coverage`, `infection`
  - **Ainda não implementados:** `users`, `load-smoke`, `load-load`, `load-stress`, `load-concurrency`, `obs`, `web` como stubs ("not implemented yet").
- Redis via **phpredis**; drivers `cache=redis`, `queue=redis`, `session=array`; skeleton enxuto (ADR 0023).
- CaptainHook (pre-commit: Pint + PHPStan; commit-msg: `ramsey/conventional-commits`) em `run-mode: docker`, executando via `docker compose exec app`: o host não precisa de PHP, mas o commit exige o ambiente no ar (`make up`).
- GitHub Actions via Docker Compose (ADR 0021): build da imagem com cache do buildx, `make up` e `make qa`.

## Fora de escopo
Qualquer regra de domínio, auth, mensageria, observabilidade.

## A decidir na fase
- ~~Verificação de Swoole/OTel no PHP 8.5 e versão do Laravel~~ — feita em 2026-11-09, sem bloqueios (ADR 0022).

## Critérios automáticos
- `make up` sobe tudo saudável; `make qa` verde (Pint, PHPStan, Deptrac, suítes, Infection sem código ainda).
- CI verde no GitHub Actions (que também valida o `make up` saudável).
- Teste de feature para `/health/ready`.

## Validação manual (usuário)
1. Copiar `.env.example` → `.env`, **trocar a porta do MySQL** (ex.: 3307) e rodar `make up` — deve subir sem conflito.
2. `make help` — conferir seções e alvos.
3. Alvo de listagem de serviços — conferir IP/porta/URL de cada um.
4. Abrir `/health/ready`, UI do RabbitMQ e do Mailpit nas URLs listadas.
5. `make qa` local.
6. Fazer um commit de teste: hook roda Pint + PHPStan e rejeita mensagem fora de Conventional Commits.

## Aprovação
- [x] Aprovado pelo usuário em: 2026-11-09
