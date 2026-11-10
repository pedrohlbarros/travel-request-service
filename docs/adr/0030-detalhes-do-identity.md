# 0030 — Detalhes do context Identity

- **Status:** Aceito
- **Data:** 2026-11-09
- **Refina:** [0004](0004-autorizacao-role-e-policies.md), [0005](0005-autenticacao-jwt.md), [0008](0008-papeis-do-redis.md)

## Contexto
Na revisão da fase 02 surgiram pontos que os ADRs 0004, 0005 e 0008 não fecham: credencial de login, hash e política de senha, resposta do cadastro e dos tokens, como o usuário autenticado chega aos outros contexts e quem o seeder cria.

## Decisão
### Usuário e senha
- Login por **e-mail**, normalizado em minúsculas, único. Nome obrigatório, de 2 a 120 caracteres.
- Hash **Argon2id** (padrão do PHP) atrás da port `PasswordHasher`; adapter com o `Hash` do Laravel.
- Senha de **8 a 72 caracteres**. Sem checagem de senha vazada (exigiria API externa).
- Login com e-mail inexistente também calcula um hash, para o tempo de resposta não revelar se o e-mail existe.

### Endpoints
- `POST /auth/register` responde **201 só com o usuário** (sem tokens). E-mail já cadastrado: 409 `/problems/email-already-registered` ([0031](0031-problem-details.md)).
- Login e refresh respondem no formato OAuth2: `{access_token, token_type: "Bearer", expires_in, refresh_token, refresh_expires_in}`. O refresh token vai no corpo JSON (sem cookie).
- `POST /auth/logout` (autenticado) recebe o `refresh_token` no corpo: o `jti` do access vai para a blacklist e a família do refresh é revogada. Responde 204.
- Rate limit (ADR 0008): login por IP + e-mail; refresh por IP (não há login no refresh).

### Chaves
- `kid` = thumbprint da chave pública (RFC 7638), calculado a partir do arquivo. Caminhos das chaves, `iss`, `aud` e tempos de vida via `.env`.

### Usuário autenticado nos outros contexts
- O guard JWT devolve um `Interfaces\Http\Shared\Auth\AuthenticatedUser` (`id`, `isAdmin`, `jti`, expiração do token), que implementa o `Authenticatable` do Laravel. O enum `Role` continua no Identity; Policies e controllers de outros contexts só enxergam Shared.
- O guard fica em `Interfaces\Http\Identity` (adapter HTTP): `Infrastructure` não pode depender de `Interfaces` (ADR 0024).

### Seeder de demonstração
- `admin@example.com` (admin) e dois usuários comuns com nomes de One Piece (`luffy@example.com`, `zoro@example.com`), mesma senha fixa documentada no README. Domínio `example.com` (RFC 2606). O seeder recusa rodar em produção.

## Consequências
- Cada request autenticado lê o usuário no MySQL e consulta a blacklist no Redis; cache do usuário só se a fase 07 mostrar necessidade.
- Cadastro com e-mail existente revela que ele está cadastrado (registrar na entrega).
