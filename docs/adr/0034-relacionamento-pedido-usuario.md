# 0034 — Relacionamento entre pedido e usuário

- **Status:** Aceito
- **Data:** 2026-11-10
- **Substitui:** do [0033](0033-detalhes-da-api-de-pedidos.md), o Resource sem nome do solicitante e o `requester_id` sem foreign key
- **Refina:** [0024](0024-modulo-shared-e-regras-do-deptrac.md)

## Contexto
O enunciado pede, nos requisitos técnicos, um relacionamento entre as ordens de viagem e o usuário autenticado, e pede que o pedido inclua o nome do solicitante. O ADR 0033 tinha deixado o pedido sem foreign key e o Resource só com `requester_id`, para não acoplar os contexts TravelRequest e Identity. O Deptrac continua proibindo que um context importe código do outro.

## Decisão
### Banco
- `travel_requests.requester_id` e `travel_requests.decided_by` têm foreign key para `users.id`, com `restrictOnDelete`. Um usuário que pediu ou decidiu um pedido não pode ser apagado, porque o pedido precisa continuar dizendo quem pediu e quem decidiu.

### Eloquent, sem acoplar os contexts
- O relacionamento fica na Infrastructure do TravelRequest, com um model próprio: `RequesterModel`. Ele aponta para a tabela `users`, só enxerga `id` e `name` e recusa qualquer gravação ou remoção (lança exceção). Quem escreve usuários continua sendo só o Identity.
- O `TravelRequestModel` ganha `requester()` e `decider()` (`BelongsTo`) e o local scope `ownedBy(UserId)`, usado no escopo de dono da listagem. O `RequesterModel` ganha `travelRequests()` (`HasMany`).
- Nos dois models, os IDs de usuário ficam como os 16 bytes crus, sem cast, para que o relacionamento compare `BINARY(16)` com `BINARY(16)`. A conversão para texto acontece no mapeamento.
- Regra que refina o 0024: um context pode **ler** a tabela de outro, só na Infrastructure e por um model próprio só de leitura. Importar código do outro context continua proibido.

### Resposta
- O `TravelRequestOutput` e o Resource trocam `requester_id` por `requester: {id, name}`, e `decided_by` passa a ser `{id, name}` ou `null`.
- A leitura (detalhe e lista) faz eager load de `requester:id,name` e `decider:id,name`. Uma página da lista custa três queries, qualquer que seja o tamanho.
- As escritas (criar, editar, aprovar, cancelar) devolvem o pedido relido pelo query service (`TravelRequestQueries::get`). Assim, um único lugar monta a resposta com os nomes. O custo é uma leitura a mais por escrita, que repõe o cache recém-invalidado.
- O nome vai para o cache junto com o pedido. Hoje nenhuma rota muda o nome de um usuário. Se isso mudar, a troca de nome precisa invalidar os pedidos daquele usuário.

### Escopo por dono
Não muda: quem cria é sempre o dono, o usuário comum só vê e lista os próprios pedidos, e só o dono edita (ADR 0003 e 0028). O admin ver todos continua como ambiguidade registrada para a entrega.

## Consequências
- O schema de TravelRequest depende da tabela `users`. Separar o Identity em outro serviço exigiria trocar a foreign key e o `RequesterModel` por uma projeção alimentada por eventos.
- Os testes de banco do TravelRequest gravam primeiro os usuários que os pedidos referenciam.
