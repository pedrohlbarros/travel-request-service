# Fase 08 — Entrega

- **Status:** Concluído (aprovado em 2026-11-11)
- **ADRs:** 0003, 0009, 0013, 0016, 0020, 0043, 0044 e a seção "Para registrar na entrega" do CLAUDE.md

## Objetivo
Deixar o repositório pronto para o avaliador: um README padrão e enxuto, documentos dedicados com o que foi construído, uma página que mostra a notificação chegando e a revisão do enunciado.

## Escopo
### 1. README padrão e enxuto (ADR 0043)
- O que é o serviço, com a tecnologia e a arquitetura resumidas.
- Tecnologias (nome e versão), pré-requisitos e instalação passo a passo, com as contas de demo.
- Perfis do Docker Compose e o que cada um contém.
- Serviços: nome, descrição mínima e URL.
- Testes e qualidade: ferramentas e régua.
- Links para a documentação e autor.
- Os prints são colocados à mão pelo usuário.

### 2. Documentos dedicados
- `docs/arquitetura.md`: contextos e camadas, containers, fluxos, concorrência, dados, cache e réplica, observabilidade, decisões e interpretações do enunciado.
- `docs/comandos.md`: todos os alvos do `Makefile`, com opções e exemplos.
- `docs/testes.md`: guia de testes (suítes, convenções, ferramentas e régua, CI).
- `docs/testes-de-carga.md`: cenários, metas, como rodar e resultados (antes em `docs/load/resultados.md`).
- `docs/api/requests.http` e `docs/api/openapi.json` (`make openapi`).

### 3. Página de notificações (ADR 0044)
- `/docs/notifications` no lugar do frontend opcional: login com contas de demo, lista com não lidas, chegada ao vivo pelo Reverb e tema claro e escuro.
- `make web` imprime a URL.

### 4. Apoio à avaliação
- `make demo`: o fluxo completo, do login à notificação e ao e-mail.

### 5. Diagramas de arquitetura
- LikeC4 no perfil `docs` (`make docs`): modelo único em `docs/architecture/`, visões numeradas em sequência (contexto, containers, observabilidade, componentes, fluxos com walkthrough e os dados reais de cada passo, deployment), listadas em `docs/arquitetura.md`.

### 6. Revisão do enunciado
- Cada item do enunciado conferido contra rotas, regras e testes (feito na fase, sem documento próprio).

## Validação manual (usuário)
1. Clonar o repositório numa pasta limpa e seguir só o README: `make setup`, `make seed` e `make demo`.
2. Abrir `/docs/notifications`, entrar como Luffy, rodar `make demo` e ver a decisão chegar.
3. `make docs`: conferir as visões em ordem no LikeC4 e tocar o fluxo 5.3 com **Start**.
4. Ler `docs/arquitetura.md`, `docs/comandos.md`, `docs/testes.md` e `docs/testes-de-carga.md`.
5. Rodar alguns requests de `docs/api/requests.http`.

## Aprovação
- [x] Aprovado pelo usuário em: 2026-11-11
