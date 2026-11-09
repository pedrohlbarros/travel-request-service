# 0003 — Regras de negócio do pedido de viagem

- **Status:** Aceito
- **Data:** 2026-11-09

## Contexto
Um pedido tem ID, solicitante, destino, data de ida, data de volta e status. O enunciado tem trechos ambíguos ou em tensão entre si. Esses pontos foram decididos e **devem ficar explícitos na entrega**.

## Decisão
1. **Status** (backed enum string): `requested` (solicitado), `approved` (aprovado), `cancelled` (cancelado).
2. **Transições:** `requested → approved` e `requested → cancelled`. Pedido aprovado **não pode** ser cancelado.
3. **Só admin muda status.** O dono do pedido não aprova nem cancela, nem como desistência.
4. **Admin pode aprovar ou cancelar o próprio pedido.** *(Ambiguidade: o enunciado não proíbe; documentar na entrega.)*
5. **Escopo de leitura:** usuário comum vê só os próprios pedidos; **admin vê todos** (consulta e listagem com os mesmos filtros). *(Ambiguidade: "cada usuário pode ver apenas as suas próprias ordens" vs. admin precisar ver os pedidos para aprovar; documentar na entrega.)*
6. **Edição pelo dono:** altera destino e datas **só enquanto o pedido está `requested`**. Aprovado ou cancelado é imutável. Status nunca é editável pelo dono.
7. **Cadastro:** cada usuário cria pedidos vinculados a si mesmo (usuário autenticado).
8. **Notificação:** ao aprovar ou cancelar, o solicitante é notificado (ADR 0007, 0020).
9. **Listagem:** filtros por status, destino, período de criação e período de viagem (ADR 0016).

## Consequências
- Seção de ambiguidades obrigatória na entrega (README ou doc linkado), cobrindo pelo menos os itens 4 e 5.
- Regras de transição e de edição vivem no domínio e têm testes dedicados.
- Códigos HTTP das violações: ADR 0006.
