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

## Carregar bases (macro `CarregarBases`)

Importa as extrações de análise de carga e roda a análise, sem copiar e colar:

1. **Alt+F8 → `CarregarBases` → Executar** (ou associe a macro a um botão).
2. Selecione um ou mais arquivos de base (`.xlsx`, `.xlsm`, `.xls`, `.xlsb`, `.csv`).
   A aba da base é encontrada sozinha em cada arquivo, pelos títulos antigos
   (`MAT LIB SAP`…) ou novos (`ATENDIDO SAP`…).
3. Com mais de um arquivo, escolha:
   - **SIM — juntar**: uma análise com todas as bases. As colunas são casadas por
     nome (a ordem pode variar entre arquivos) e a coluna `ARQUIVO ORIGEM`
     identifica de onde veio cada linha.
   - **NÃO — separar**: uma análise por base, salva ao lado de cada arquivo como
     `ANALISE INVENTARIO - <base>.xlsx`, com um resumo no final. Nesse modo o
     `HISTORICO` e o "vs. execução anterior" não são atualizados (comparariam
     bases diferentes).

Antes de alterar qualquer coisa aparece uma **prévia** (dry-run): linhas por arquivo,
colunas ausentes, arquivos que não serão carregados e a aba que será substituída.
**OK** carrega e analisa; **Cancelar** não altera nada. Os arquivos de base são
abertos só para leitura.

## Saúde da execução

Ao fim de cada análise, o PAINEL DO GESTOR ganha o bloco **SAÚDE DA EXECUÇÃO**: cada
etapa com status e tempo, a **fonte de preços** usada (aba interna ou arquivo externo,
com a data) e os avisos não fatais. Se algo falhou, o subtítulo do painel mostra
"ANÁLISE INCOMPLETA" (ou "ANÁLISE COM AVISOS"). O bloco vai junto no arquivo exportado
pelo modo "separar". O DIAGNÓSTICO BASE confere a idade da base de preços (limite
`PRECOS_MAX_DIAS` na CONFIG, padrão 90 dias).

## Abas persistentes

A macro apaga e recria as abas de análise a cada execução, mas estas **não** entram
nessa lista e acumulam histórico entre rodadas:

- `HISTORICO` — uma linha por obra por execução (situação e se mudou)
- `HISTORICO KPI` — oculta; um registro agregado por execução (PEPs, reprovados,
  alertas, divergências, valor em risco, nota da base). É a fonte do
  "vs. execução anterior" nos cards do PAINEL DO GESTOR
- `CONFIG` — oculta; parâmetros editáveis
- `EQUIVALENCIAS` — oculta; materiais equivalentes, no layout `COD MAT | DESC MAT | UND |
  TIPO | FAMILIA | EQUIVALENCIA | PAR DE EQUIVALENCIA` (mesmo número em
  `EQUIVALENCIA` = códigos intercambiáveis). Na ANALISE SAP x PRJ, quando 2+ códigos
  do grupo aparecem no mesmo PEP4, só são ADERENTE se a quantidade TOTAL do grupo
  for o mesmo número no SAP e no PRJ (sem tolerância, em qualquer família): SAP 3
  num código e PRJ 9 no outro = NÃO ADERENTE; 9 x 10 também. Cada linha mantém o próprio código e descrição; o MOTIVO cita
  os dois e o total. Um código em dois grupos une os grupos.
  Nasce com a lista padrão da área (grupos 1–34, sem o 10 e o 28, que repetiam o 8
  e o 24) e o grupo 35 (`105300003` = `105300005`, que muda de número quando a área
  passa a usar o dele). A aba é do usuário: a macro só acrescenta
  grupos de versões novas da lista padrão (versão guardada no nome oculto
  `EQUIV_VERSAO`), sem repetir códigos nem reinserir o que foi apagado
- `LOG EXECUCAO` — oculta; log da última rodada

`CONFIG` e `EQUIVALENCIAS` ficam ocultas: para editar, clique com o botão direito
em qualquer guia → **Reexibir** → escolha a aba. A próxima execução oculta de novo.

Apagar `HISTORICO KPI` não quebra nada: a próxima execução recria a aba e os cards
apenas ficam sem a linha de variação até haver duas rodadas registradas.

## Referências exigidas

O módulo usa constantes `mso*` (`msoShapeRoundedRectangle`, `msoTrue`,
`msoAlignCenter`, `msoAnchorMiddle`). Em **Ferramentas → Referências** deve estar
marcada a **Microsoft Office xx.x Object Library**; sem ela essas constantes também
disparam `Variável não definida`.
