# 0004 — Autorização com enum role + Policies

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- Usuário tem `role` modelado como enum PHP: `user` e `admin`.
- Regras de acesso (ver, editar, mudar status) implementadas em **Policies** do Laravel, na camada `Interfaces`.

## Alternativas descartadas
Tabelas próprias de roles/permissions, `spatie/laravel-permission`, permissions como claims no JWT. Mais granularidade do que o enunciado pede.
