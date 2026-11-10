# Fase 01 — Domínio TravelRequest

- **Status:** Concluída (2026-11-09)
- **ADRs:** 0002, 0003, 0006, 0012, 0014, 0015, 0017, 0018, 0028, 0029

## Objetivo
Modelar o pedido de viagem em PHP puro, com as regras de negócio completas e testadas, sem framework nem banco.

## Escopo
- Agregado `TravelRequest` com ID UUIDv7 gerado no domínio, solicitante (referência ao usuário), destino (string), datas de ida/volta, status, versão, timestamps.
- VOs (ex.: ID, destino, período da viagem com validação ida ≤ volta), enum `TravelRequestStatus` (`requested`, `approved`, `cancelled`).
- Regras: transições `requested → approved/cancelled`; aprovado não cancela; edição de dados só em `requested`; incremento de versão em toda mudança.
- Eventos de domínio (aprovado, cancelado; demais se necessários) registrados no agregado.
- Exceções de domínio específicas (base para os `/problems/...`).
- Ports: repositório, clock, gerador de ID, publicação de eventos.
- Fakes in-memory dessas ports em `tests/Support`.
- Casos de uso (`<Ação>UseCase` + Input/Output) da Application testados com fakes.

## Fora de escopo
HTTP, Eloquent, autenticação, mensageria real.

## Critérios automáticos
- Suíte `unit` verde; Infection em Domain+Application com MSI ≥ 90% e Covered MSI ≥ 95%.
- Deptrac: zero dependência de `Illuminate\*` em Domain/Application.

## Validação manual (usuário)
1. Ler a lista de testes (`make test-unit` com testdox) — cada regra do ADR 0003 tem um teste com nome legível.
2. `make infection` — conferir relatório de MSI.
3. Revisar o agregado e os VOs.

## Aprovação
- [x] Aprovado pelo usuário em: 2026-11-09
