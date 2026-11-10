# 0025 — Providers por context e camada, sem `app/`

- **Status:** Aceito
- **Data:** 2026-11-09
- **Substitui:** trecho "`app/` só bootstrap" do [0002](0002-arquitetura-ddd-hexagonal.md) e o wiring em `app/Providers` do [0024](0024-modulo-shared-e-regras-do-deptrac.md)

## Contexto
O diretório `app/` existia só para os ServiceProviders. Concentrar o wiring ali cria uma camada "Bootstrap" que depende de tudo e fica fora da estrutura por contexts.

## Decisão
- **Sem `app/`.** Os ServiceProviders vivem na camada e no context que configuram:
  - `Infrastructure\<Context>\...ServiceProvider`: liga ports aos adapters (ex.: `Infrastructure\Shared\SharedServiceProvider`);
  - `Interfaces\Http\<Context>\...ServiceProvider`: rotas e policies do context, quando existirem.
- Registro em `bootstrap/providers.php`.
- O app path do Laravel aponta para `src/Infrastructure` (`useAppPath` em `bootstrap/app.php`), porque `route:list`, os `make:*` e a descoberta de namespace exigem um diretório de aplicação mapeado no PSR-4.
- `resources/views` existe vazio, porque o `optimize` (`view:cache`) exige o diretório.
- O Deptrac não tem mais a camada Bootstrap. Os providers obedecem às regras da camada onde estão (`Infrastructure` não depende de `Interfaces`).

## Consequências
- Cada context é dono do próprio wiring.
- `make:*` do Laravel gera arquivos em `src/Infrastructure`. Gerador não é usado no projeto, e o que for gerado precisa ser movido.
