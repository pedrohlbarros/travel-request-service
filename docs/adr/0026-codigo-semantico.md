# 0026 — Código semântico e nomes

- **Status:** Aceito
- **Data:** 2026-11-09
- **Complementa:** [0014](0014-padrao-de-codigo.md)

## Contexto
O código deve dizer o que faz e por quê, sem depender de quem o escreveu (pessoa ou IA). Regex inline, valores mágicos e nomes vagos obrigam o leitor a decifrar.

## Decisão
1. **Sem valor mágico inline:** regex, limites, formatos e versões vão em constante com nome que diga o significado (`SAFE_CLIENT_REQUEST_ID`, `LONGEST_ACCEPTED_REQUEST_ID`).
2. **API com nome antes de regex à mão:** se o framework ou uma lib já valida, usa-se a API (`Str::isUuid($id, 7)`).
3. **Grupos de regex com nome** (`(?<trace_id>...)`), nunca `$matches[1]`.
4. **Nomes descrevem intenção:** sem nomes de uma ou duas letras e sem genéricos (`$tmp`, `$data`, `$res`, `$val`, `$obj`, `$arr`, `$str`...). Exceções: `$i` em loop numérico, `$id`, `$ip`. Booleanos como pergunta (`$isReady`, `$hasExpired`); mapas como `$valueByKey` (`$isHealthyByCheckName`).
5. **Testes:** entradas nomeadas pelo cenário (`$unsafeRequestIds` com chaves `tooShort`, `headerInjection`...), constantes para valores repetidos e arrange/act/assert separados por linha em branco.
6. **Comentário explica o porquê**, nunca o quê. Se precisa explicar o quê, o nome está ruim.

## Verificação
- Regra PHPStan própria `tools/PHPStan/NoVagueVariableNamesRule`: barra nomes curtos e genéricos na declaração (parâmetros, atribuições, `foreach`, `catch`, propriedades), em `src`, `tests` e `tools`. Testada na suíte `tools`.
- Os itens 1, 2, 3, 5 e 6 ficam para revisão.
