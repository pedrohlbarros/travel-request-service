# Planos de implementação

Uma fase por arquivo. Fluxo de cada fase:

1. **Proposto** → o usuário revisa o plano e aprova o início (status **Aprovado para início**). A branch `phase/NN-...` nasce da `main` atualizada.
2. Implementação com testes, seguindo os ADRs.
3. `make qa` verde + roteiro de **validação manual** do plano executado pelo usuário.
4. **Aprovação manual explícita** do usuário → status **Concluído** com data.
5. Claude propõe a tabela de commits, o usuário confirma, Claude commita um a um na branch `phase/NN-...`, faz push e abre o PR. O CI roda no PR e o usuário faz o merge. Só então começa a próxima fase.

Nenhuma decisão nova é tomada dentro de uma fase sem aval; o que surgir vira pergunta e, se aceito, ADR.

| # | Fase | Status |
|---|---|---|
| [00](00-fundacao.md) | Fundação | Concluído (2026-11-09) |
| [01](01-dominio-travel-request.md) | Domínio TravelRequest | Concluído (2026-11-09) |
| [02](02-identity.md) | Identity | Concluído (2026-11-10) |
| [03](03-api-pedidos.md) | API de pedidos | Concluído (2026-11-10) |
| [04](04-mensageria.md) | Mensageria | Concluído (2026-11-10) |
| [05](05-notification.md) | Notification | Concluído (2026-11-10) |
| [06](06-observabilidade.md) | Observabilidade | Concluído (2026-11-11) |
| [07](07-carga.md) | Testes de carga | Proposto |
| [08](08-entrega.md) | Entrega | Proposto |
| [09](09-frontend-opcional.md) | Frontend (opcional) | Proposto |
