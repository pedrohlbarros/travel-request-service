# 0019 — Automação das checagens

- **Status:** Aceito (trecho de CI substituído por [0021](0021-ci-com-docker-compose.md))
- **Data:** 2026-11-09

## Decisão
- **Makefile como ponto de entrada**, tudo rodando **dentro do container** — o avaliador não precisa de PHP na máquina.
- O Makefile é o **catálogo completo de ações**, organizado em seções e autodocumentado (`make help` lista os alvos por seção):
  - **Ambiente:** subir/derrubar, logs, status e **listar IP/porta/URL de cada serviço**.
  - **Setup:** setup inicial, chaves JWT, migrations, seed.
  - **Usuários:** criar usuário e admin.
  - **Qualidade:** lint, análise estática, arquitetura, `qa` (tudo).
  - **Testes:** cada suíte separada, todas, cobertura, Infection.
  - **Carga:** cada cenário k6 (smoke, load, stress, concorrência).
  - **Perfis:** `obs`, `load`, `web`.
- Toda ação nova útil para testar ou validar ganha um alvo.
- **GitHub Actions** no push/PR com o mesmo pipeline de qualidade (forma de execução: ver [0021](0021-ci-com-docker-compose.md)). Badge no README.
- **CaptainHook:**
  - `pre-commit`: Pint nos arquivos staged + PHPStan.
  - `commit-msg`: valida **Conventional Commits** (mensagens em inglês).
  - Testes rodam no CI, não no hook.
