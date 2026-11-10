# 0022 — Imagem base do app e versões dos serviços

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
Verificação na fase 00 (2026-11-09): PHP 8.5.12 com Swoole 6.2.5, extensão OpenTelemetry 1.5.0 e PCOV 1.0.12 compilam e rodam (corrotinas e `SWOOLE_HOOK_ALL` ok) tanto em Debian quanto em Alpine. Com as mesmas extensões e sem dependências de build:

| | Debian (`php:8.5-cli`) | Alpine (`php:8.5-cli-alpine`) |
|---|---|---|
| Imagem com extensões | 878 MB | 282 MB |
| Build sem cache | ~200 s | ~240 s |

## Decisão
- **App:** `php:8.5-cli-alpine`, Swoole 6.2.x via PECL (sem pré-release).
- **MySQL** 8.4 (LTS) · **Redis** 8.x (minor estável mais recente) · **RabbitMQ** 4.3-management · **Mailpit** v1.33.
- Tags fixadas no minor.

## Consequências
- Imagem ~3x menor; Dockerfile mais simples (`apk --virtual` para dependências de build).
- **Ressalva de performance (musl):** o `malloc` do musl é mais lento que o da glibc. O Zend MM cobre as alocações das requests, mas Swoole e extensões usam o alocador do sistema. A **fase 07** compara Alpine com uma variante Debian sob load e stress; se a diferença for relevante, a troca fica restrita ao Dockerfile (novo ADR).
- Exporter OTel via OTLP http/protobuf (gRPC/protobuf compilam devagar no musl).
- `iconv` sem `//TRANSLIT` (sem impacto: Laravel usa `portable-ascii`).
