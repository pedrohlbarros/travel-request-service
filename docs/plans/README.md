# Planos de implementação

Uma fase por arquivo. Fluxo de cada fase:

1. **Proposto** → o usuário revisa o plano e aprova o início (status **Aprovado para início**).
2. Implementação com testes, seguindo os ADRs.
3. `make qa` verde + roteiro de **validação manual** do plano executado pelo usuário.
4. **Aprovação manual explícita** do usuário → status **Concluído** com data. Só então começa a próxima fase.

Nenhuma decisão nova é tomada dentro de uma fase sem aval; o que surgir vira pergunta e, se aceito, ADR.

| # | Fase | Status |
|---|---|---|
| [00](00-fundacao.md) | Fundação | Proposto |
| [01](01-dominio-travel-request.md) | Domínio TravelRequest | Proposto |
| [02](02-identity.md) | Identity | Proposto |
| [03](03-api-pedidos.md) | API de pedidos | Proposto |
| [04](04-mensageria.md) | Mensageria | Proposto |
| [05](05-notification.md) | Notification | Proposto |
| [06](06-observabilidade.md) | Observabilidade | Proposto |
| [07](07-carga.md) | Testes de carga | Proposto |
| [08](08-entrega.md) | Entrega | Proposto |
| [09](09-frontend-opcional.md) | Frontend (opcional) | Proposto |
