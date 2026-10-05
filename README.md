# INVENTARIO — módulo VBA

`inventario.bas` é o módulo padrão (`Option Explicit`) usado pela pasta de trabalho
`INVENTARIO 3.0.xlsx`.

## Como instalar no Excel

Importe o módulo em vez de colar o código:

1. Alt+F11 (VBE) → no Explorador de Projetos, clique com o botão direito no módulo
   `inventario` existente → **Remover inventario** → **Não** (não exportar).
2. **Arquivo → Importar Arquivo…** → selecione `inventario.bas`.
3. **Depurar → Compilar VBAProject** — deve compilar sem mensagens.

Colar código longo diretamente na janela de código do VBE pode truncar linhas
(inclusive linhas `Dim`), o que produz o erro de compilação
`Variável não definida`. Sempre use Importar.

## Abas persistentes

A macro apaga e recria as abas de análise a cada execução, mas estas **não** entram
nessa lista e acumulam histórico entre rodadas:

- `HISTORICO` — uma linha por obra por execução (situação e se mudou)
- `HISTORICO KPI` — oculta; um registro agregado por execução (PEPs, reprovados,
  alertas, divergências, valor em risco, nota da base). É a fonte do
  "vs. execução anterior" nos cards do PAINEL DO GESTOR
- `CONFIG` — parâmetros editáveis
- `EQUIVALENCIAS` — materiais equivalentes, no layout `COD MAT | DESC MAT | UND |
  TIPO | FAMILIA | EQUIVALENCIA | PAR DE EQUIVALENCIA` (mesmo número em
  `EQUIVALENCIA` = códigos intercambiáveis). Na ANALISE SAP x PRJ, os itens do grupo
  no mesmo PEP4 são avaliados pela soma das quantidades. Nasce com os grupos 1–5
  da área e o grupo 6 (`105300003` = `105300005`); a aba do layout antigo
  (`GRUPO | COD MAT`) é migrada uma vez, mantendo as linhas acrescentadas pelo usuário
- `LOG EXECUCAO` — oculta; log da última rodada

Apagar `HISTORICO KPI` não quebra nada: a próxima execução recria a aba e os cards
apenas ficam sem a linha de variação até haver duas rodadas registradas.

## Referências exigidas

O módulo usa constantes `mso*` (`msoShapeRoundedRectangle`, `msoTrue`,
`msoAlignCenter`, `msoAnchorMiddle`). Em **Ferramentas → Referências** deve estar
marcada a **Microsoft Office xx.x Object Library**; sem ela essas constantes também
disparam `Variável não definida`.
