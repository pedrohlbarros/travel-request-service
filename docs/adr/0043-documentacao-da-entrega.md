# 0043 — Documentação da entrega

- **Status:** Aceito
- **Data:** 2026-11-11
- **Refina:** [0009](0009-infra-docker-e-perfis.md), [0013](0013-documentacao.md), [0019](0019-automacao-de-qualidade.md)

## Contexto
A fase 08 fecha o projeto. O enunciado pede um README com as decisões, a instalação, a execução via Docker, a configuração do ambiente e os testes. Tudo o mais que foi construído (comandos, arquitetura, testes, carga) precisa continuar acessível, sem inflar o README.

## Decisão
### README padrão e enxuto
- Seções, nesta ordem:
  1. título e badge do CI;
  2. o que é o serviço, com a tecnologia e a arquitetura resumidas e links para os ADRs e para a arquitetura;
  3. tecnologias, uma linha por área, com nome e versão;
  4. pré-requisitos;
  5. instalação passo a passo, com a troca de portas no `.env` e as contas de demonstração;
  6. perfis do Docker Compose e o que cada um contém;
  7. serviços, com nome, descrição mínima e URL;
  8. testes e qualidade, com as ferramentas e a régua;
  9. links para a documentação;
  10. autor.
- Tom descritivo, sem emojis e sem detalhes de implementação. Os prints são colocados à mão nas seções onde fizerem sentido.

### Documentos dedicados em `docs/`
- `arquitetura.md`: contextos e camadas, containers, fluxos (autenticação, criação, decisão → outbox → notificação, falhas), concorrência, dados, cache e réplica, observabilidade, e as decisões e interpretações do enunciado.
- `comandos.md`: todos os alvos do `Makefile`, por seção, com opções e exemplos.
- `testes.md`: suítes, convenções, ferramentas e régua, contratos travados em teste, testes manuais e CI.
- `testes-de-carga.md`: cenários, metas, como rodar e resultados (antes em `docs/load/resultados.md`).
- `api/`: catálogo de erros, coleção `requests.http` para o HTTP Client do PhpStorm e `openapi.json`, exportado por `make openapi`.

### Apoio à avaliação
- `make demo` (`tools/demo.php`, dentro do container do app) percorre o fluxo inteiro e imprime cada passo: login, criação, aprovação com `If-Match`, notificação entregue e e-mail no Mailpit.

### Diagramas C4 (LikeC4)
- Um modelo único em `docs/architecture/`, em arquivos numerados (`00-specification`, `01-model`, `02-views`, `03-flows`, `04-deployment`). Essa ordem dos arquivos é a do menu do LikeC4.
- As visões são numeradas em sequência:
  - 1. contexto;
  - 2. containers;
  - 3. observabilidade;
  - 4. componentes (4.1 a 4.4);
  - 5. fluxos (5.1 a 5.5);
  - 6. deployment.
- Os fluxos têm walkthrough (Start, Previous, Next), e cada passo traz uma nota em Markdown com os dados reais de uma execução: request e resposta, SQL, linha do outbox, CloudEvent, inbox, notificação, e-mail e evento do WebSocket. Ids e tokens aparecem encurtados.
- O LikeC4 (`likec4/likec4:1.60.0`) sobe no perfil `docs` com recarga ao vivo (`make docs`, porta `FORWARD_LIKEC4_PORT`, 5173). `docs/arquitetura.md` lista as visões em ordem.

## Consequências
- O README cobre o que o enunciado pede. A descrição inicial resume as decisões, e o detalhe fica em `docs/arquitetura.md` e nos ADRs.
- Não há prints automatizados nem exportação dos diagramas para imagem. As imagens do README são tiradas à mão.
- As notas dos fluxos são uma foto de uma execução. Se o formato de um payload mudar, a nota precisa ser atualizada à mão.
- `make docs` passa a subir os diagramas, e o export do OpenAPI vira `make openapi`.
