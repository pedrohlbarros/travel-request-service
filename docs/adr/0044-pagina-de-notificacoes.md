# 0044 — Página de notificações no lugar do frontend opcional

- **Status:** Aceito
- **Data:** 2026-11-11
- **Substitui:** o perfil `web` do [0009](0009-infra-docker-e-perfis.md), o frontend com OTel no navegador do [0010](0010-observabilidade.md) e a fase 09
- **Refina:** [0020](0020-canais-de-notificacao.md), [0037](0037-detalhes-do-notification.md)

## Contexto
A fase 09 previa um frontend opcional: build estático com Nginx no perfil `web`, OTel JS, login, CRUD de pedidos e ações de admin. A fase 05 deixou uma página Blade simples em `/docs/notifications`, onde se colava o token e via o JSON cru chegando pelo Reverb. O enunciado não pede frontend, e o que importa mostrar é a notificação chegando ao solicitante.

## Decisão
- A página `/docs/notifications` cumpre o papel do frontend opcional. A finalidade continua a mesma: mostrar a chegada da notificação. Ela continua servida pela API, só em `APP_ENV=local`, em HTML, CSS e JS sem build.
- O que a página faz:
  - **Login:** formulário com atalhos para as contas de demo (Luffy, Zoro, admin). O refresh do access token é automático, e o logout envia o refresh token para revogar os dois.
  - **Notificações:** lista as existentes (`GET /notifications`), com contador de não lidas, filtro "Não lidas", "marcar como lida" e "marcar todas como lidas".
  - **Chegada ao vivo:** pelo canal privado `users.{id}` do Reverb, com destaque, aviso e o status da conexão (desconectado, conectando, ao vivo, erro). Como a notificação gravada e o evento têm o mesmo id, a lista não duplica.
  - **Como provocar:** um passo a passo para gerar uma notificação, com `make demo`.
  - **Aparência:** tema claro e escuro, que segue o sistema e pode ser trocado.
- `make web` imprime a URL da página. O perfil `web`, o Nginx e o OTel no navegador saem do escopo.
- A fase 09 fica registrada como substituída pela 08.

## Consequências
- O trace começa na API, não no navegador. O trace ponta a ponta (HTTP → outbox → publish → consume) continua igual (ADR 0038).
- A página não cria nem decide pedidos. Para provocar uma notificação, use `make demo`, a coleção `docs/api/requests.http` ou a API.
- Pusher JS e Laravel Echo vêm de CDN com versão fixa, então a página precisa de internet no navegador.
