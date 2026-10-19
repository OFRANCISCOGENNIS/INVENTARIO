<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

use Inventario\Application\UseCase\ModoCarga;
use Inventario\Application\UseCase\SolicitacaoAnalise;
use Inventario\Domain\Support\Formatador;
use Inventario\Presentation\Aplicacao;

/**
 * inventario analisar BASE1.xlsx [BASE2.csv ...] [--modo=juntar|separar]
 *   [--precos=BASE_DE_PRECOS.xlsx] [--saida=pasta] [--formato=xlsx,json] [--previa]
 */
final class ComandoAnalisar implements Comando
{
    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function nome(): string
    {
        return 'analisar';
    }

    public function descricao(): string
    {
        return 'Analisa uma ou mais bases (xlsx, xlsm, xls, ods, csv) e gera o relatorio';
    }

    public function executar(Argumentos $args, Saida $out): int
    {
        if ($args->posicionais === []) {
            $out->erro('Informe ao menos um arquivo de base. Ex.: inventario analisar base.xlsx');

            return 2;
        }
        $modo = ModoCarga::tryFrom(strtolower($args->opcao('modo', 'juntar'))) ?? ModoCarga::JUNTAR;
        $arquivos = [];
        foreach ($args->posicionais as $p) {
            $arquivos[$p] = basename($p);
        }
        $sol = new SolicitacaoAnalise($arquivos, $modo, $args->opcao('precos'));
        $uc = $this->app->analisarInventario();

        // 1) LER + PREVIA (dry-run)
        $leitura = $uc->ler($sol);
        $out->linha('PREVIA DA CARGA (' . ($modo === ModoCarga::JUNTAR ? 'juntar' : 'separar') . ')');
        $linhas = [];
        foreach ($leitura->previa($modo === ModoCarga::JUNTAR) as $p) {
            $linhas[] = [$p['arquivo'], $p['aba'], Formatador::inteiro($p['linhas']),
                $p['obrigatoriasFaltando'] !== [] ? 'FALTAM: ' . implode(', ', $p['obrigatoriasFaltando'])
                    : ($p['ausentes'] !== [] ? 'sem: ' . implode(', ', $p['ausentes']) . ' (fica vazia)' : 'ok')];
        }
        $out->tabela(['ARQUIVO', 'ABA', 'LINHAS', 'COLUNAS'], $linhas);
        foreach ($leitura->erros as $e) {
            $out->linha('  NAO sera carregado: ' . $e);
        }
        if ($args->flag('previa')) {
            return $leitura->vazia() ? 1 : 0;
        }

        // 2) ANALISAR + EXPORTAR
        $res = $uc->executar($sol, $leitura);
        foreach ($res->erros as $e) {
            $out->erro('ERRO: ' . $e);
        }
        $pasta = $args->opcao('saida', $this->app->pastaSaidas);
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }
        $formatos = array_filter(array_map('trim', explode(',', $args->opcao('formato', 'xlsx'))));
        $exp = $this->app->exportadores();
        foreach ($res->analises as $ctx) {
            $rel = $this->app->montador()->montar($ctx);
            $out->linha('');
            $out->linha("== {$ctx->base->nome}: {$rel->selo}");
            $p = $ctx->painel;
            if ($p !== null) {
                $out->linha(sprintf('   PEPs %d | aprovados %d | reprovados %d | alertas %d | diverg. preco %d | valor em risco %s',
                    $p->peps, $p->aprovados, $p->reprovados, $p->alertas, $p->divergenciasPreco, Formatador::kpi($p->valorEmRisco)));
            }
            if ($ctx->diagnostico !== null) {
                $out->linha(sprintf('   Saude da base: %s (%s)', Formatador::geral($ctx->diagnostico->nota()), $ctx->diagnostico->rotulo()));
            }
            foreach ($ctx->saude->etapas() as $e) {
                if (!$e->ok) {
                    $out->linha("   FALHA [{$e->etapa}]: {$e->erro}");
                }
            }
            foreach ($ctx->saude->avisos() as $a) {
                $out->linha('   AVISO: ' . $a);
            }
            foreach ($formatos as $f) {
                if (!isset($exp[$f])) {
                    $out->erro("formato desconhecido: {$f} (use xlsx ou json)");
                    continue;
                }
                $arq = $pasta . '/' . Aplicacao::nomeSaida($ctx->base->nome) . '.' . $exp[$f]->extensao();
                $out->linha('   -> ' . $exp[$f]->exportar($rel, $arq));
            }
        }
        if ($modo === ModoCarga::SEPARAR && count($res->analises) > 1) {
            $out->linha('');
            $out->linha('Modo separar: HISTORICO e "vs. execucao anterior" nao foram atualizados (bases diferentes).');
        }

        return $res->analises === [] ? 1 : 0;
    }
}
