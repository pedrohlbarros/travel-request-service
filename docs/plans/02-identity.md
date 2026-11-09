# Fase 02 — Identity

- **Status:** Proposto
- **ADRs:** 0004, 0005, 0008, 0016, 0017

## Objetivo
Usuários e autenticação JWT completos, protegendo a API.

## Escopo
- Domínio Identity: usuário (UUIDv7, nome, login/e-mail, hash de senha, `role` enum `user`/`admin`).
- Persistência Eloquent (`BINARY(16)`), migrations.
- Ports + adapter `lcobucci/jwt`: RS256, `kid`, claims `sub/iat/exp/nbf/jti/iss/aud`, validação de `iss`/`aud`.
- `artisan identity:keys:generate` (chaves em `storage/keys/`, fora do git) e JWKS em `/.well-known/jwks.json`.
- Refresh token opaco rotativo: tabela `refresh_tokens` (hash SHA-256, família, expiração, rotação/revogação), reuso revoga a família.
- Blacklist de `jti` no Redis; guard do Laravel próprio.
- Endpoints: register (sempre `user`), login, refresh, logout, me.
- Rate limit 5/min por IP+login em login e refresh, 429 com `Retry-After`.
- `artisan identity:create-admin`; seeder com 1 admin + 2 users de demo.
- Base do Problem Details (handler de exceções RFC 9457) se ainda não existir.
- Makefile: alvos para gerar chaves, criar usuário, criar admin, rodar seed.

## Critérios automáticos
- Feature tests: register/login/refresh/logout/me, token expirado, `aud`/`iss` errados, assinatura inválida, refresh reutilizado revoga a família, logout invalida o access, rate limit 429.
- Unit tests do domínio Identity; Infection dentro da meta.

## Validação manual (usuário)
1. `make` alvo de criar admin e de criar usuário.
2. Login via curl/HTTP client, decodificar o JWT (jwt.io) e conferir header `kid`/`alg` e claims.
3. Abrir `/.well-known/jwks.json`.
4. Refresh duas vezes com o mesmo token → o segundo falha e a família é revogada.
5. Errar a senha 6 vezes → 429.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
