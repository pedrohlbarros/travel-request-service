# 0028 — Modelagem do domínio TravelRequest

- **Status:** Aceito
- **Data:** 2026-11-09
- **Refina:** [0003](0003-regras-de-negocio-do-pedido.md), [0006](0006-concorrencia-versionamento-otimista.md), [0017](0017-identificadores-uuidv7.md)

## Contexto
Na revisão da fase 01 surgiram pontos que os ADRs anteriores não cobrem: como o Domain (que não depende de nada) trata UUID, quais validações o pedido tem além das transições, quem edita o pedido e quais casos de uso e eventos entram agora.

## Decisão
### Identificadores
- O UUIDv7 é gerado por uma port `IdentifierGenerator` (Application), com adapter em Infrastructure (`Str::uuid7()`).
- Os VOs de ID (`TravelRequestId`, `UserId`) validam o formato canônico v7 com regex em constante (`Domain\Shared\ValueObject\Uuid7`). O Domain continua sem vendor; a regra Domain → nada do ADR 0024 não ganha exceção.
- `UserId` no context TravelRequest é só uma referência ao usuário do Identity (sem dependência entre contexts).

### Validações
- **Destino:** com trim, de 2 a 120 caracteres (contados em caracteres, não bytes).
- **Período:** ida ≤ volta (mesmo dia permitido) e ida ≥ hoje (UTC, pelo `Clock`), checado na criação e na edição.

### Edição
- Só o **solicitante** edita destino e datas, inclusive quando é admin. A regra fica no domínio (`changeDetails(editorId, ...)`) e lança `TravelRequestNotOwned`, que a API traduz para 404 (ADR 0006).
- Toda mudança (edição, aprovação, cancelamento) incrementa `version`. O caso de uso compara a versão esperada (`If-Match`) com a carregada e o repositório grava condicionado à versão carregada; divergência nos dois casos é `TravelRequestVersionMismatch` (412).
- A restrição "só admin muda status" fica na Policy (ADR 0004, fase 03); o domínio registra quem aprovou/cancelou.

### Casos de uso e eventos da fase 01
- Casos de uso `CreateTravelRequest`, `ChangeTravelRequestDetails`, `ApproveTravelRequest`, `CancelTravelRequest` e `GetTravelRequest` (dono vê o próprio, admin vê todos; alheio → `TravelRequestNotFound`), nomeados conforme o [0029](0029-organizacao-por-tipo-e-use-cases.md).
- A listagem com filtros e cursor fica na fase 03, junto com o query service.
- Eventos de domínio só `TravelRequestApproved` e `TravelRequestCancelled`, com IDs e estado do pedido. Os dados do solicitante (nome/e-mail) exigidos pelo evento gordo (ADR 0007) são decididos na fase 04.

### Ports
- `TravelRequestRepository` em `Domain\TravelRequest\Repository`.
- `Clock`, `IdentifierGenerator` e `DomainEventPublisher` em `Application\Shared\Port`. O agregado recebe ID e "agora" por parâmetro.

## Consequências
- O regex de UUIDv7 é mantido no repo, mas só existe num lugar e tem testes.
- Admin que quiser corrigir um pedido alheio não consegue: o solicitante edita ou o admin cancela.
