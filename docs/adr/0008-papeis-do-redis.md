# 0008 — Papéis do Redis

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
1. **Fila de jobs internos** (driver de queue do Laravel), separada dos eventos de integração no RabbitMQ.
2. **Cache de leitura:**
   - Detalhe por ID cacheado e invalidado na escrita (casa com o `ETag`).
   - Listagens cacheadas com chave = hash(escopo + filtros + cursor) + **contador de geração por escopo** (`owner:{id}` e `admin`). Toda escrita incrementa as gerações afetadas; chaves antigas expiram por TTL curto.
3. **Blacklist de JWT** por `jti`, com TTL igual ao `exp`.
4. **Rate limiting só em auth:** `/auth/login` e `/auth/refresh` com 5/min por IP+login, resposta 429 em Problem Details com `Retry-After`.

## Consequências
- **A API autenticada não tem rate limit, de propósito, para não distorcer os testes de carga (k6 mede o teto real).** Registrar na entrega; em produção entraria um limite por usuário.
