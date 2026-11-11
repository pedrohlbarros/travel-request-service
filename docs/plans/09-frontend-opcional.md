# Fase 09 — Frontend (opcional)

- **Status:** Substituída pela fase 08: a página de notificações (`/docs/notifications`) cumpre o papel do frontend opcional (ADR 0044)
- **ADRs:** 0009, 0010, 0020

## Objetivo
Interface simples para o trace começar no navegador e para ver as notificações em tempo real.

## Escopo
- Perfil `web`: build estático servido por Nginx; OTel JS exportando para o Collector.
- Login, listar/criar pedidos, ações de admin, notificações via broadcast (Reverb).
- Stack do frontend a decidir no início da fase.

## Validação manual (usuário)
1. Aprovar um pedido pela tela e ver no Jaeger um trace que começa no navegador.
2. Ver a notificação chegando em tempo real na tela do solicitante.

## Aprovação
- [ ] Aprovado pelo usuário em: ____
