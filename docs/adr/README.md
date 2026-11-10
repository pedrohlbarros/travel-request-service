# Architecture Decision Records

Uma decisão por arquivo: contexto, decisão, consequências. Numeração sequencial, nunca reaproveitada. Para mudar uma decisão, crie um ADR novo e marque o antigo como "Substituído por NNNN".

| # | Decisão | Status |
|---|---|---|
| [0001](0001-stack-e-runtime.md) | Stack e runtime: PHP 8.5, Laravel, Octane + Swoole, MySQL | Aceito |
| [0002](0002-arquitetura-ddd-hexagonal.md) | DDD + Hexagonal, 3 bounded contexts, camadas no topo | Aceito (`app/` substituído por 0025) |
| [0003](0003-regras-de-negocio-do-pedido.md) | Regras de negócio do pedido e ambiguidades do enunciado | Aceito |
| [0004](0004-autorizacao-role-e-policies.md) | Autorização com enum `role` + Policies | Aceito |
| [0005](0005-autenticacao-jwt.md) | Autenticação JWT (RS256, 15 min / 7 dias, refresh rotativo no MySQL) e usuários | Aceito |
| [0006](0006-concorrencia-versionamento-otimista.md) | Concorrência (ETag/If-Match) e códigos 428/412/409/404/403 | Aceito |
| [0007](0007-mensageria-rabbitmq-e-outbox.md) | Mensageria com RabbitMQ + Transactional Outbox | Aceito |
| [0008](0008-papeis-do-redis.md) | Papéis do Redis | Aceito |
| [0009](0009-infra-docker-e-perfis.md) | Docker Compose e perfis | Aceito |
| [0010](0010-observabilidade.md) | Observabilidade: OTel, Jaeger, Prometheus, Grafana | Aceito |
| [0011](0011-qualidade-e-testes.md) | PHPUnit, Infection e testes de carga com k6 | Aceito |
| [0012](0012-destino-como-string.md) | Destino como string no pedido | Aceito |
| [0013](0013-documentacao.md) | Organização da documentação e README enxuto | Aceito |
| [0014](0014-padrao-de-codigo.md) | Padrão de código: Pint (PER), PHPStan max, Deptrac, strict/final/readonly, docblock mínimo | Aceito |
| [0015](0015-camada-application-cqrs.md) | Casos de uso: Command/Query + Handler, sem bus | Aceito |
| [0016](0016-convencoes-da-api.md) | API: /api/v1, ações de status, cursor, snake_case, RFC 9457, Scramble | Aceito |
| [0017](0017-identificadores-uuidv7.md) | IDs UUIDv7 gerados no domínio | Aceito |
| [0018](0018-padrao-de-testes.md) | Testes: espelho de src/, MySQL real, fakes, Infection MSI ≥ 90% | Aceito |
| [0019](0019-automacao-de-qualidade.md) | Makefile, GitHub Actions, CaptainHook | Aceito (CI substituído por 0021) |
| [0020](0020-canais-de-notificacao.md) | Canais de notificação: mail, database, broadcast (Reverb) | Aceito |
| [0021](0021-ci-com-docker-compose.md) | CI com Docker Compose (substitui trecho de CI do 0019) | Aceito |
| [0022](0022-imagem-base-e-versoes.md) | Imagem base Alpine e versões dos serviços (ressalva de performance do musl) | Aceito |
| [0023](0023-dependencias-e-drivers-da-fundacao.md) | Dependências e drivers da fundação: phpredis, php-amqplib, PCOV, CaptainHook, skeleton enxuto, namespaces | Aceito |
| [0024](0024-modulo-shared-e-regras-do-deptrac.md) | Módulo Shared por camada e regras do Deptrac (refina 0002) | Aceito (wiring em `app/` substituído por 0025) |
| [0025](0025-providers-por-contexto-sem-app.md) | Providers por context e camada, sem `app/` | Aceito |
| [0026](0026-codigo-semantico.md) | Código semântico e nomes (complementa 0014) | Aceito |
| [0027](0027-complexidade-cognitiva.md) | Complexidade cognitiva no PHPStan (função ≤ 9, classe ≤ 40) | Aceito |
