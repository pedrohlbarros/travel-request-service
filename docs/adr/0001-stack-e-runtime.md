# 0001 — Stack e runtime

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
O enunciado exige um microsserviço em Laravel (versão mais recente possível), API REST e MySQL. Fora dessa restrição, a escolha natural seria Hyperf. Performance é um dos critérios de avaliação.

## Decisão
- **PHP 8.5**.
- **Laravel** na versão estável mais recente.
- **Laravel Octane** com **Swoole** como servidor de aplicação.
- **MySQL** como banco relacional, com migrations.
- API **REST**.

## Consequências
- O worker do Octane é reaproveitado entre requests: nada de estado por request em singletons, propriedades estáticas ou serviços com estado mutável.
- Swoole é extensão C compilada na imagem. Verificar na implementação o suporte a PHP 8.5 e a compatibilidade com a extensão OpenTelemetry e com corrotinas.
