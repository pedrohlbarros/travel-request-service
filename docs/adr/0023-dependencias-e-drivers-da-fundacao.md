# 0023 — Dependências e drivers da fundação

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
Na revisão da fase 00 surgiram escolhas de dependência e configuração não cobertas pelos ADRs anteriores.

## Decisão
- **Redis:** cliente **phpredis** (extensão C na imagem; streams hookados pelo Swoole). Predis descartado por custo sob carga.
- **AMQP:** **`php-amqplib`** (PHP puro, publisher confirms). Usado no `/health/ready` e, na fase 04, no relay do outbox. `ext-amqp` descartada para não somar outra extensão C.
- **Cobertura:** driver **PCOV** (só no stage dev/test). Meta global ≥ 85% (ADR 0018) verificada por script sobre o clover.
- **Infection sem código:** `--ignore-msi-with-no-mutations` apenas na fase 00; removido na fase 01.
- **CaptainHook** em `run-mode: docker` via `docker compose exec app` (host sem PHP; commit exige `make up`). `commit-msg` com **`ramsey/conventional-commits`**.
- **Drivers Laravel:** `cache=redis`, `queue=redis`, `session=array` (API stateless).
- **Skeleton enxuto:** sem frontend (Vite, `package.json`, `resources/js|css`, welcome), sem `app/Models`, `app/Http` e migration de `users` (Identity cria na fase 02). Ficam só as migrations de `jobs`/`failed_jobs`.
- **Namespaces (PSR-4):** camadas na raiz — `Domain\`, `Application\`, `Infrastructure\`, `Interfaces\` → `src/<Camada>/`; sem `App\` (ver 0025); testes em `Tests\` espelhando (`Tests\Domain\...`).
- **OTel na fase 00:** sem dependência; só middleware de `request_id`/`traceparent` e variáveis `OTEL_*` (`OTEL_SDK_DISABLED=true`). Extensão e SDK na fase 06.

## Consequências
- Mais uma extensão C (phpredis) no Dockerfile.
- Commits locais dependem do ambiente Docker no ar.
