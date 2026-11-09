# 0002 — Arquitetura DDD + Hexagonal

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
O critério de avaliação pede organização e qualidade de código e uso dos recursos do Laravel (Eloquent, Middlewares, Requests, Resources). Optou-se por não usar a estrutura padrão `app/` do Laravel para o domínio.

## Decisão
### Bounded contexts
- **TravelRequest** — pedidos de viagem, transições de status, eventos de domínio.
- **Identity** — usuários, login, tokens, roles.
- **Notification** — consome os eventos de domínio (via outbox/RabbitMQ) e envia notificações pelos N canais com `notify` do Laravel.

### Layout: camadas no topo, contexts dentro
```
src/
  Domain/          TravelRequest/ Identity/ Notification/
  Application/     TravelRequest/ Identity/ Notification/
  Infrastructure/  Persistence/Eloquent/... Messaging/RabbitMQ/...
  Interfaces/
    Http/          TravelRequest/ Identity/   # Controllers, FormRequests, Resources, Policies
app/               # só bootstrap do Laravel (Providers etc.)
```

## Consequências
- Regra de dependência: `Domain` não depende de nada; `Application` depende só de `Domain`; `Infrastructure` e `Interfaces` dependem das camadas internas. Laravel não entra em `Domain`.
- Os recursos do Laravel exigidos pelo avaliador (Eloquent, Middlewares, FormRequests, Resources, Policies) vivem em `Infrastructure`/`Interfaces`, como adapters.
- Um context fica espalhado por quatro árvores. A regra de dependência fica visível na estrutura.
- Detalhes (agregado PHP puro vs. Eloquent, mapeamento, como a regra de dependência é verificada) ficam para os planos e o padrão de código.
