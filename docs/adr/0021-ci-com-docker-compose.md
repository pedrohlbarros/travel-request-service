# 0021 — CI com Docker Compose

- **Status:** Aceito
- **Data:** 2026-11-09
- **Substitui:** trecho de CI do [0019](0019-automacao-de-qualidade.md) ("MySQL/Redis/RabbitMQ como services")

## Contexto
O Makefile roda tudo dentro do container do app (ADR 0019). Rodar o CI com PHP instalado no runner e os serviços como `services:` do GitHub Actions faria o CI usar outra fonte de PHP, extensões e versões de serviço, sem testar o Dockerfile nem o `compose.yaml`.

## Decisão
- O GitHub Actions (push/PR) builda a imagem do projeto com cache do buildx no GHA, sobe o ambiente com `make up` e roda `make qa`, exatamente como localmente.
- MySQL, Redis e RabbitMQ vêm do próprio `compose.yaml`, não de `services:` do Actions.
- Evolução prevista, se o tempo de CI incomodar: dividir em jobs paralelos (`static`: lint/stan/arch; `tests`: suítes/cobertura/Infection) consumindo a mesma imagem em cache, sempre via alvos do Makefile.

## Alternativas descartadas
- `setup-php` + services do Actions: mais rápido, mas diverge do ambiente local e não testa Dockerfile/compose.
- Imagem do projeto como job container (GHCR) + services: exige registry e dois jobs encadeados, continua duplicando os serviços e não testa o compose.

## Consequências
- Paridade total entre CI e ambiente local. O critério "`make up` sobe tudo saudável" passa a ser verificado automaticamente.
- Build mais lenta (Swoole compilado na imagem), mitigada pelo cache de camadas.
- Badge do CI no README continua valendo (ADR 0019).
