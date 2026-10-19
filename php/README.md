# INVENTARIO — versão PHP (MVP)

Porta em PHP da análise feita pelo módulo VBA `inventario.bas`: lê a base SAP x PRJ
e gera as mesmas abas (PAINEL DO GESTOR, ANALISE SAP x PRJ, RESUMO, RACIONALIZACAO COM,
PRECO UNITARIO, ALERTA CRITICO, RANKING DE RISCO, DIAGNOSTICO BASE, DEVOLUCOES,
HISTORICO, LOG EXECUCAO) em **XLSX**, **JSON** e numa **página web**.

## Requisitos e instalação

- PHP 8.2+ com `mbstring`, `zip`, `xml`, `gd` (PhpSpreadsheet)
- Composer

```bash
cd php
composer install
```

## Uso

### Linha de comando

```bash
bin/inventario analisar BASE.xlsx                         # gera storage/saidas/ANALISE INVENTARIO - BASE.xlsx
bin/inventario analisar jan.xlsx fev.csv --modo=juntar    # uma análise com as duas bases (coluna ARQUIVO ORIGEM)
bin/inventario analisar jan.xlsx fev.csv --modo=separar   # uma análise por base (sem histórico)
bin/inventario analisar BASE.xlsx --previa                # só a prévia (dry-run): linhas e colunas ausentes
bin/inventario analisar BASE.xlsx --precos="BASE DE PRECOS.xlsx" --formato=xlsx,json --saida=/pasta
bin/inventario config                                     # lista os parâmetros (antiga aba CONFIG)
bin/inventario config TOL_ADERENCIA_PCT 0,15              # altera um parâmetro
bin/inventario equivalencias                              # grupos de materiais equivalentes
bin/inventario historico                                  # KPIs de cada execução
```

Formatos de base aceitos: `xlsx`, `xlsm`, `xls`, `ods`, `csv` (`;` ou `,`, UTF-8 ou
Windows-1252). A aba da base é achada pelos títulos antigos (`MAT LIB SAP`…) ou novos
(`ATENDIDO SAP`…). `xlsb` não é suportado: salve como `xlsx`.

### Página web

```bash
php -S 127.0.0.1:8000 -t public
```

Abra `http://127.0.0.1:8000`. A página tem três telas:

- **Nova análise**: arraste ou selecione as bases, escolha *Juntar* ou *Separar* (com mais de
  um arquivo), inclua a base de preços MIN/MAX se quiser e clique em **Executar análise**.
- **Resultados**: indicadores clicáveis (filtram a tabela), pesquisa, filtro por status e por
  faixa de preço, ordenação por coluna, 25 linhas por página e detalhe SAP x PRJ com o motivo
  do veredito ao clicar numa linha. Exporta os registros filtrados ou todos em CSV e baixa o
  relatório completo em XLSX/JSON. Em *Relatório completo* ficam as demais abas (painel,
  alertas, racionalização, preço unitário, ranking, diagnóstico, devoluções).
- **Ajuda**: passo a passo e o significado de cada status.

Status de cada linha: **Não aderente** (SIT MAT não aderente), **Sem correspondência** (só SAP
ou só PRJ têm quantidade), **Verificar** (item aderente em obra reprovada ou sem UC) e
**Aderente**. A tela usa as fontes Inter e JetBrains Mono, servidas pelo próprio site
(`public/assets/fonts`).

Em produção, aponte o servidor web para `public/` e mantenha `storage/` fora da raiz pública.
Bases `xlsx`/`xlsm` são lidas e gravadas em fluxo: uma base de 30 mil linhas leva cerca de
10 s e cabe nos 512 MB do plano gratuito do Render.

### Publicar o site na internet (Render)

O repositório já tem `render.yaml` (na raiz) e `php/Dockerfile`:

1. Crie uma conta em https://render.com entrando com o GitHub.
2. **New → Blueprint**, escolha o repositório `INVENTARIO` e confirme.
3. Quando pedir `INVENTARIO_SENHA`, digite uma senha. O site passa a pedir usuário
   `inventario` e essa senha (mude o usuário com a variável `INVENTARIO_USUARIO`).
4. Ao terminar o deploy, o Render mostra o endereço (`https://analise-inventario-xxxx.onrender.com`).

No plano gratuito o site "dorme" sem uso (o primeiro acesso demora ~1 min) e o disco é
apagado a cada novo deploy: `config.json`, equivalências e histórico voltam ao padrão.
Para guardá-los, adicione um disco persistente (plano pago) montado em `/var/www/app/storage`.

### Pasta de dados

Padrão `php/storage` (ou a variável `INVENTARIO_DADOS`):

| Arquivo | Equivale a |
|---|---|
| `config.json` | aba CONFIG (criado com os padrões; chaves novas são acrescentadas) |
| `equivalencias.json` | aba EQUIVALENCIAS (nasce com a lista padrão v4; mesmo `grupo` = códigos intercambiáveis) |
| `historico.json` | abas HISTORICO e HISTORICO KPI |
| `BASE DE PRECOS.xlsx` | arquivo externo de preços MIN/MAX (opcional) |
| `saidas/` | arquivos gerados |

Fonte de preços (cadeia, a primeira que existir): aba `BASE PRECOS`/`BASE DE PRECOS`
dentro do arquivo da base → arquivo de `--precos` → `BASE DE PRECOS.xlsx` na pasta de
dados. Preço de referência do alerta UC SUBVALORIZADO: aba `PRECOS` (código, preço) do
arquivo da base, ou o ponto médio MIN/MAX.

## Arquitetura

```
src/
  Domain/            regras de negócio puras (sem I/O)
    Base/            BaseInventario, LinhaBase, Coluna, MapeadorCabecalho (aliases), TipoOD
    Config/          Parametros (valores padrão da CONFIG)
    Regra/           RegraAderencia, RegrasOD, AvaliadorLinha → AvaliacaoLinha
    Equivalencia/    CatalogoEquivalencias (union-find), ListaPadraoEquivalencias
    Analise/         SapPrj (veredito PEP4/PEP3), Resumo (por obra), Racionalizacao (NT.006)
    Nt006/           MapaNT006 + ClassificadorDescricao
    Preco/           FaixaPreco, TabelaPrecos, PrecosReferencia, AnalisadorPrecoUnitario, FontePrecos (porta)
    Alerta/          GeradorAlertas + uma classe por regra (Regra/*)
    Diagnostico/     DiagnosticoBase (nota de saúde 0-100)
    Ranking/         CalculadoraRisco (score + PEPs sem baixa)
    Painel/          MontadorPainel, Variacao (vs. execução anterior)
    Historico/       KpiExecucao, RegistroObra, RepositorioHistorico (porta)
  Application/       orquestração
    Pipeline/        Pipeline + uma Etapa por aba; SaudeExecucao (status/tempo/avisos)
    UseCase/         AnalisarInventario (juntar / separar), SolicitacaoAnalise
    Carga/           leitura das bases, prévia, junção por nome de coluna
    Porta/           interfaces que a infraestrutura implementa
    Relatorio/       MontadorRelatorio → Relatorio (abas/blocos, independente de formato)
  Infrastructure/    I/O
    Leitura/         LeitorExcel (PhpSpreadsheet), LeitorCsv
    Preco/           cadeia de fontes de preço
    Persistencia/    repositórios JSON (config, equivalências, histórico)
    Exportacao/      ExportadorXlsx, ExportadorJson
  Presentation/      Aplicacao (raiz de composição), Cli/ (comandos), Web/ (controlador + HTML)
```

Padrões usados:

- **Pipeline** — cada aba é uma `Etapa`; falha numa etapa não derruba as outras, fica
  registrada na SAÚDE DA EXECUÇÃO (como o `FimEtapa` do VBA).
- **Strategy** — cada alerta é uma classe (`RegraAlertaLinha` / `RegraAlertaAgregada`);
  leitores de planilha por formato.
- **Chain of Responsibility** — fontes de preço.
- **Repository + Ports & Adapters** — o domínio não conhece arquivo nem banco; trocar
  JSON por MySQL é implementar as interfaces de `Application/Porta` e
  `Domain/Historico/RepositorioHistorico`.
- **Builder** — `MontadorRelatorio` monta um relatório neutro que XLSX, JSON e HTML desenham.

### Como estender

- **Novo alerta:** crie uma classe em `src/Domain/Alerta/Regra` implementando
  `RegraAlertaLinha` e inclua-a em `GeradorAlertas::padrao()`.
- **Novo parâmetro:** acrescente em `Parametros::PADRAO`; o `config.json` ganha a chave
  na próxima execução.
- **Nova aba:** crie uma `Etapa`, adicione em `Pipeline::padrao()` e monte a aba em
  `MontadorRelatorio`.

## Testes

```bash
composer test        # ou: vendor/bin/phpunit
```

91 testes: os testes de lógica do `TestarLogicaInventario` do VBA (aderência, sinal por
OD, equivalentes, aliases, alertas, diagnóstico, NT.006) mais testes de integração do
pipeline completo sobre `exemplos/base_exemplo.csv`.

## Diferenças em relação ao VBA

- Material com descrição `LACRE P/ MEDIDOR` era contado como **medidor** na regra
  LACRE x MEDIDOR e classificado como **MEDIDOR** na racionalização NT.006. Aqui é lacre.
- Números nos textos não têm a vírgula sobrando do formato `#,##0.##` (`1.234` em vez de `1.234,`).
- Formatação condicional/KPIs dinâmicos que reagem a filtro, botões e hiperlinks do Excel
  não existem no XLSX gerado; as cores por status e o autofiltro, sim.
- A migração da aba EQUIVALENCIAS do layout antigo (coluna GRUPO) não foi portada: o
  `equivalencias.json` já nasce no layout atual.
