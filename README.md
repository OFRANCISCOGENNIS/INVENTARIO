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

## Referências exigidas

O módulo usa constantes `mso*` (`msoShapeRoundedRectangle`, `msoTrue`,
`msoAlignCenter`, `msoAnchorMiddle`). Em **Ferramentas → Referências** deve estar
marcada a **Microsoft Office xx.x Object Library**; sem ela essas constantes também
disparam `Variável não definida`.
