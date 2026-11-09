# 0005 — Autenticação JWT e usuários

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
O enunciado pede autenticação simples por token (como JWT). Alternativas avaliadas: Sanctum (token opaco), Passport (OAuth2), IdP externo (Keycloak), PASETO.

## Decisão
### Emissão e verificação
- **JWT emitido pelo próprio serviço** (context Identity), com **`lcobucci/jwt`**.
- Assinatura **RS256**; chave pública exposta em `GET /.well-known/jwks.json`.
- Emissão e verificação atrás de ports; `lcobucci/jwt` fica no adapter. Guard do Laravel implementado por nós.

### Claims
- Header: `alg`, `typ`, **`kid`** (rotação de chaves: o JWKS pode publicar mais de uma chave).
- Payload: `sub`, `iat`, `exp`, `jti`, **`iss`**, **`aud`**, **`nbf`** — `iss` e `aud` validados na verificação.
- **Sem `role`** e sem dados de perfil (nome/e-mail) no token: role resolvido pelo usuário carregado; nada de PII no header.

### Tempos de vida
- **Access token: 15 min.**
- **Refresh token: 7 dias**, deslizante a cada rotação.

### Refresh token
- Opaco e rotativo, guardado no **MySQL** (tabela `refresh_tokens`): **hash SHA-256** do token (nunca o valor), `user_id`, `family_id`, `expires_at`, `rotated_at`/`revoked_at`.
- **Reuso** de um token já rotacionado **revoga a família inteira**.
- Logout revoga o access token (blacklist de `jti` no Redis, ADR 0008) e a família do refresh.

### Chaves
- Par RSA gerado no setup (`make setup` / entrypoint na primeira subida) por `artisan identity:keys:generate`, gravado em `storage/keys/` (**fora do git**). Caminho e `kid` via env.

### Usuários
- `POST /api/v1/auth/register` público cria sempre `role=user`.
- Admins só por seeder ou comando (`artisan identity:create-admin`).
- Seeder cria 1 admin + 2 users de demonstração, documentados no README.

## Consequências
- Trocar para um IdP externo no futuro muda só o adapter. Citar como evolução na entrega.
- Mudança de role vale na hora (não depende de novo token), ao custo de resolver o usuário por request (candidato a cache).
