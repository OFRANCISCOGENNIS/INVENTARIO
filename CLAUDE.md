# INVENTARIO — Análise de Inventário SAP x PRJ

Este repositório tem duas implementações da mesma análise.

## Estrutura

- `inventario.bas` — módulo VBA (Excel "INVENTARIO 3.0"). Arquivo único por desenho (não
  dividir em vários módulos).
- `php/` — porta em PHP 8.2+ (Domain / Application / Infrastructure / Presentation),
  CLI `php/bin/inventario`, site em `php/public` (publicado no Render via `render.yaml`).
- `README.md` (VBA) e `php/README.md` (PHP) documentam as regras de negócio.

## Regras do VBA (`inventario.bas`)

- Manter fim de linha **CRLF** e codificação Latin-1; nunca converter para LF/UTF-8.
- Sem literais acentuados no código (corrompem na importação); usar `ChrW` quando precisar.
- Parâmetros de entrada `ByVal`; `Private Const` só na seção de declarações; no máximo 24
  continuações `_` por instrução; `And`/`Or` não fazem curto-circuito; `IIf` avalia os dois
  ramos; ler chave inexistente de `Scripting.Dictionary` cria a chave; não usar nomes que
  colidem com palavras reservadas (`aS`, `dO`...).
- Toda regra nova ganha teste em `TestarLogicaInventario`.
- Parâmetros novos entram em `CfgPadrao` (aba CONFIG); equivalências novas entram em
  `EquivPadrao` com nova versão (`EQUIV_VERSAO_ATUAL`) e também em
  `php/src/Domain/Equivalencia/ListaPadraoEquivalencias.php`.
- **Sempre que o `.bas` mudar, enviar o arquivo atualizado ao usuário.**

## Regras do PHP (`php/`)

- `declare(strict_types=1)`, PSR-4 `Inventario\`, regras de negócio só em `src/Domain`.
- Rodar `cd php && vendor/bin/phpunit` antes de commitar; regra nova = teste novo.
- Chaves de array que são códigos/PEPs podem virar inteiros no PHP: converter com
  `(string)` ao iterar.
- XLSX é lido e gravado em fluxo (`LeitorXlsx`, `ExportadorXlsx`); não voltar a montar a
  planilha inteira na memória (o Render gratuito tem 512 MB).

## Idioma

- Responder ao usuário em português do Brasil.

## Harmonia entre ferramentas (Ruflo, Superpowers, graphify)

Cada ferramenta tem um papel; este bloco decide os conflitos entre elas. Vale quando a
ferramenta estiver instalada na sessão e fica acima das instruções de cada uma.

| Fase | Ferramenta | Papel |
|------|------------|-------|
| Entender o código | **graphify** | Mapa do projeto (`graphify-out/`) consultado antes de varrer arquivos |
| Decidir e planejar | **Superpowers** | Processo: brainstorming, plano, TDD, depuração, verificação |
| Coordenar e lembrar | **Ruflo** | Memória entre sessões e ganchos de sessão/edição |
| Executar | **Claude Code** | Quem de fato edita, testa, faz commit e push |

- **Entender:** se `graphify-out/graph.json` existir, perguntas sobre arquitetura começam
  por `graphify query`/`path`/`explain`; confirmar no código-fonte antes de editar.
  O graphify não lê VBA: o `inventario.bas` entra no grafo por extração semântica e
  precisa ser reextraído à mão quando mudar. `graphify-out/` não vai para o git.
- **Planejar:** `brainstorming` e `writing-plans` só para regra ou funcionalidade nova com
  decisão em aberto. Ajuste pontual pedido de forma clara (trocar um fator, tirar uma
  regra, corrigir um bug localizado) vai direto, sem cerimônia. Specs e planos ficam no
  scratchpad da sessão, não em `docs/superpowers/` (não criar documentação sem pedido).
- **TDD:** o `test-driven-development` do Superpowers é a forma de cumprir "regra nova =
  teste novo": teste em `TestarLogicaInventario` (VBA) e no PHPUnit (PHP) antes da regra.
- **Depurar:** `systematic-debugging` antes de propor correção para bug ou teste falhando.
- **Verificar:** `verification-before-completion` com os comandos reais deste projeto:
  `cd php && vendor/bin/phpunit` e o verificador estático do `.bas`. Não há
  `package.json` nem `npm test` neste projeto.
- **Agentes:** um único escritor por árvore de trabalho (as três ferramentas concordam).
  Subagentes servem para pesquisa ou leitura em paralelo e para extração do graphify;
  swarm do Ruflo só se o usuário pedir.
- **Branches:** trabalhar na branch designada da sessão. `using-git-worktrees` e
  `finishing-a-development-branch` não criam branch nova, não abrem PR e não fazem merge;
  o PR só é mesclado quando o usuário disser "mescla".
- **Ruflo:** a memória (`memory_search`/`memory_store`) é opcional e nunca bloqueia o
  trabalho. O bloco genérico de instruções do Ruflo e o gancho de roteamento por mensagem
  foram retirados para economizar contexto; a documentação dele está em `.claude/`.

## Regras gerais (herdadas do Ruflo)

- Fazer o que foi pedido, nem mais nem menos; preferir editar arquivo existente a criar novo.
- Ler o arquivo antes de editar; nunca commitar segredos, credenciais ou `.env`.
- Nunca adicionar trailer `Co-Authored-By` nos commits.
- Rodar os testes depois de mudar código e antes de commitar.
