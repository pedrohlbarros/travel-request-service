# 0014 — Padrão de escrita de código

- **Status:** Aceito
- **Data:** 2026-11-09

## Decisão
- **Estilo:** Laravel Pint com preset **PER Coding Style** (sucessor do PSR-12), mais regras extras: `strict_types`, imports ordenados, `final`. Autoload PSR-4.
- **Análise estática:** PHPStan + Larastan no **nível max**, **sem baseline**, com `phpstan-strict-rules` e `phpstan-deprecation-rules`.
- **Regra de dependência:** **Deptrac** com as camadas (`Domain`, `Application`, `Infrastructure`, `Interfaces`) e os contexts declarados; violação quebra o build.
- **Escrita PHP:**
  - `declare(strict_types=1)` em todo arquivo; tipos nativos em parâmetros, retornos e propriedades; sem `mixed` fora de adapters.
  - Classes `final` por padrão (exceção justificada); VOs, DTOs, Commands, Queries e Events como `readonly class`.
  - `Domain` e `Application` não usam Facades nem helpers globais (`now()`, `auth()`, `config()` etc.): dependências entram por injeção via ports. Facades e helpers só em `Infrastructure`/`Interfaces`.
- **Docblock:** só o que o tipo não diz — generics/shapes para o PHPStan (`list<Foo>`, `array{...}`), `@throws` de exceções de domínio e o porquê de regra não óbvia. Sem docblock redundante com a assinatura.
- **Idioma:** código e comentários em inglês.

## Alternativas descartadas
Rector (não adotado), Psalm, PHP_CodeSniffer, PHPat/PHPArkitect.
