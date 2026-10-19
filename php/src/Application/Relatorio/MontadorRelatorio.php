<?php

declare(strict_types=1);

namespace Inventario\Application\Relatorio;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Analise\Racionalizacao\LinhaRacionalizacao;
use Inventario\Domain\Analise\Resumo\ResumidorPep3;
use Inventario\Domain\Analise\Resumo\ResumoObra;
use Inventario\Domain\Analise\SapPrj\LinhaAnalise;
use Inventario\Domain\Diagnostico\Verificacao;
use Inventario\Domain\Historico\RegistroObra;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Ranking\ObraRisco;
use Inventario\Domain\Ranking\PepSemBaixa;
use Inventario\Domain\Support\Formatador;

/**
 * Builder: transforma o resultado da analise nas abas do relatorio, na
 * mesma ordem e com os mesmos titulos do arquivo gerado pelo VBA.
 */
final class MontadorRelatorio
{
    public function montar(ContextoAnalise $c): Relatorio
    {
        $gerado = $c->agora->format('d/m/Y H:i');
        $abas = [
            $this->painel($c, $gerado),
            $this->analise($c),
            $this->resumo($c),
            $this->racionalizacao($c),
            $this->precoUnitario($c),
            $this->alertas($c),
            $this->ranking($c),
            $this->diagnostico($c, $gerado),
            $this->devolucoes($c),
        ];
        if ($c->historico !== []) {
            $abas[] = new Aba('HISTORICO', 'HISTORICO', 'Situacao de cada obra nesta execucao vs. a anterior', [
                self::bloco('', ['RUN', 'PEP3NIVEL', 'SITUACAO', 'VALOR NAO ADER', 'MUDOU'],
                    array_map(static fn (RegistroObra $r): array => array_values($r->paraArray()), $c->historico),
                    moeda: [3]),
            ], '3C3C3C');
        }
        $abas[] = new Aba('LOG EXECUCAO', 'LOG EXECUCAO', 'Log da ultima rodada', [
            self::bloco('', ['LOG'], array_map(static fn (string $l): array => [$l], $c->saude->linhasLog())),
        ], '787878', [110]);

        return new Relatorio($c->base->nome, $gerado, $c->saude->selo(), $abas);
    }

    private function painel(ContextoAnalise $c, string $gerado): Aba
    {
        $p = $c->painel;
        $blocos = [];
        if ($p !== null) {
            $var = static fn (string $k): string => $p->variacao($k)?->texto ?? '';
            $blocos[] = self::bloco('INDICADORES', ['INDICADOR', 'VALOR', 'VS. EXECUCAO ANTERIOR'], [
                ['Valor total da obra', Formatador::kpi($p->valorTotal), ''],
                ['PEPs analisados', Formatador::inteiro($p->peps), $var('peps')],
                ['PEPs aprovados', Formatador::inteiro($p->aprovados) . '  (' . Formatador::pct($p->pctAprovados()) . ')', ''],
                ['PEPs reprovados', Formatador::inteiro($p->reprovados), $var('reprovados')],
                ['Alertas criticos', Formatador::inteiro($p->alertas), $var('alertas')],
                ['Divergencias de preco', Formatador::inteiro($p->divergenciasPreco), $var('divergencias')],
                ['Sobrepreco potencial', Formatador::kpi($p->sobrepreco), ''],
                ['Valor em risco (reprov.)', Formatador::kpi($p->valorEmRisco), ''],
            ]);
            $maxQ = max(1, $p->alertasPorTipo[0]['qtd'] ?? 1);
            $blocos[] = self::bloco('ALERTAS CRITICOS POR TIPO', ['TIPO DE ALERTA', 'QTD', 'PARTICIPACAO'],
                array_map(static fn (array $t): array => [
                    $t['tipo'],
                    $t['qtd'],
                    str_repeat("\u{2588}", max(1, (int) floor($t['qtd'] / $maxQ * 24))) . '  ' . Formatador::pct($t['participacao']),
                ], $p->alertasPorTipo),
                0, Paleta::ALERTA, vazio: 'Nenhum alerta critico encontrado. Parabens!');
        }
        $blocos[] = $this->saude($c);

        $sub = "Analise de Inventario  -  Base: {$c->base->nome}   |   Gerado em {$gerado}";
        if (!$c->saude->ok()) {
            $sub .= "   |   \u{26A0} " . $c->saude->selo();
        }

        return new Aba('PAINEL DO GESTOR', 'PAINEL DO GESTOR', $sub, $blocos, '111827', [34, 26, 26, 60]);
    }

    private function saude(ContextoAnalise $c): Bloco
    {
        $s = $c->saude;
        $linhas = [];
        foreach ($s->etapas() as $e) {
            $linhas[] = [$e->etapa, $e->ok ? 'OK' : 'FALHA', number_format($e->segundos, 1, ',', ''), $e->erro];
        }
        $linhas[] = ['FONTE DE PRECOS (faixa MIN/MAX)', $s->precoRuim() ? 'ATENCAO' : 'OK', '', $s->descricaoFontePrecos()];
        if ($s->fonteReferencia() !== '') {
            $linhas[] = ['PRECO DE REFERENCIA (UC SUBVAL.)', 'OK', '', $s->fonteReferencia()];
        }
        foreach ($s->avisos() as $a) {
            $linhas[] = ['AVISO', 'ATENCAO', '', $a];
        }

        return self::bloco('SAUDE DA EXECUCAO  -  ' . $s->selo(), ['ETAPA', 'STATUS', 'TEMPO (s)', 'DETALHE'],
            $linhas, 1, Paleta::VERIFICACAO);
    }

    private function analise(ContextoAnalise $c): Aba
    {
        $r = $c->sapPrj();
        $kpis = self::bloco('INDICADORES', ['ODs (PEP4)', 'PEP3 APROVADOS', 'PEP3 REPROVADOS', 'VALOR SAP', 'VALOR NAO ADER.'], [[
            Formatador::inteiro($r->totalPep4),
            Formatador::pct($r->pctAprovados()),
            Formatador::pct($r->pctReprovados()),
            Formatador::kpi($r->valorSap),
            Formatador::kpi($r->valorNaoAderente),
        ]]);
        $hdr = ['PEP3NIVEL', 'PEP4NIVEL', 'NOTA', 'CLASSE', 'COD MAT', 'VALOR', 'DESC MAT', 'UND', 'MAT LIB SAP',
            'MAT PRJ CAD', 'TIPO', 'FAMILIA', 'SIT MAT', 'APROVACAO', 'MOTIVO', 'MOTIVO DEVOLUCAO PEP3'];
        $linhas = array_map(static fn (LinhaAnalise $l): array => array_values($l->paraArray()), $r->linhas);

        return new Aba('ANALISE SAP x PRJ', "\u{26A1} ANALISE SAP x PROJETO",
            'Inventario Inteligente  |  ' . count($linhas) . ' linhas',
            [$kpis, self::bloco('', $hdr, $linhas, 13, Paleta::APROVACAO, [5])],
            '0A2540', [24, 26, 10, 11, 11, 11, 50, 6, 13, 14, 6, 24, 18, 15, 60, 48]);
    }

    private function resumo(ContextoAnalise $c): Aba
    {
        return new Aba('RESUMO SAP x PRJ', 'RESUMO POR OBRA (PEP3)  -  1 linha por obra', count($c->resumo) . ' obras', [
            self::bloco('', ['PEP3NIVEL', 'SITUACAO', 'ODs (PEP4)', 'VALOR OBRA', 'VALOR NAO ADER', 'FAMILIAS REPROVADAS', 'MOTIVO DEVOLUCAO'],
                array_map(static fn (ResumoObra $o): array => array_values($o->paraArray()), $c->resumo),
                1, Paleta::APROVACAO, [3, 4]),
        ], '1F4E79', [26, 13, 10, 15, 16, 40, 46]);
    }

    private function racionalizacao(ContextoAnalise $c): Aba
    {
        return new Aba('RACIONALIZACAO COM', 'RACIONALIZACAO COM  -  o que veio na obra x o previsto (NT.006)',
            count($c->racionalizacao) . ' materiais COM', [
                self::bloco('', ['PEP4NIVEL', 'PEP3NIVEL', 'COD MATERIAL', 'MATERIAL', 'FAMILIA', 'LIGADO A (referencia)', 'VEIO', 'PREVISTO', 'STATUS', 'OBSERVACAO'],
                    array_map(static fn (LinhaRacionalizacao $l): array => array_values($l->paraArray()), $c->racionalizacao),
                    8, Paleta::RACIONALIZACAO),
            ], '2E75B6', [26, 24, 14, 42, 18, 40, 9, 12, 16, 46]);
    }

    private function precoUnitario(ContextoAnalise $c): Aba
    {
        $div = count(array_filter($c->precoUnitario, static fn (LinhaPrecoUnitario $p): bool => $p->status->divergente()));

        return new Aba('PRECO UNITARIO', 'PRECO UNITARIO  -  PU (VALOR/QTD) x faixa MIN/MAX',
            "{$div} divergencia(s) | fonte: " . $c->saude->descricaoFontePrecos(), [
                self::bloco('', ['PEP3NIVEL', 'PEP4NIVEL', 'TIPO', 'COD MAT', 'DESC MAT', 'UND', 'QTD', 'VALOR', 'PU (VALOR/QTD)', 'MIN PU', 'MAX PU', 'STATUS', 'OBSERVACAO', 'TIPO OD'],
                    array_map(static fn (LinhaPrecoUnitario $l): array => array_values($l->paraArray()), $c->precoUnitario),
                    11, Paleta::PRECO, [6, 7, 8, 9, 10]),
            ], 'B07C00', [24, 26, 8, 13, 46, 6, 10, 12, 14, 11, 11, 18, 40, 9]);
    }

    private function alertas(ContextoAnalise $c): Aba
    {
        $res = $c->alertas();
        $chips = implode('   |   ', array_map(
            static fn (array $r): string => $r['tipo'] . " \u{2022} " . Formatador::inteiro($r['qtd']),
            $res->resumo(),
        ));

        return new Aba('ALERTA CRITICO', "\u{26A0}  PAINEL DE ALERTAS CRITICOS", $chips, [
            self::bloco('', ['TIPO ALERTA', 'PEP3NIVEL', 'PEP4NIVEL', 'COD MAT', 'DESC MAT', 'FAMILIA', 'VALOR', 'QTD', 'PRECO UNIT', 'REFERENCIA', 'MOTIVO'],
                array_map(static fn (Alerta $a): array => array_values($a->paraArray()), $res->alertas()),
                0, Paleta::ALERTA, [6, 7, 8], 'Nenhum alerta critico encontrado nesta base.'),
        ], 'B00000', [22, 24, 26, 12, 46, 18, 12, 9, 12, 14, 60]);
    }

    private function ranking(ContextoAnalise $c): Aba
    {
        $rk = $c->ranking;
        $obras = $rk?->obras ?? [];
        $sem = $rk?->semBaixa ?? [];

        return new Aba('RANKING DE RISCO', 'RANKING DE RISCO POR OBRA  -  por onde comecar a auditoria', count($obras) . ' obras', [
            self::bloco('', ['#', 'PEP3NIVEL', 'VALOR OBRA', 'SITUACAO', 'ALERTAS', 'DIVERG PRECO', 'SOBREPRECO R$', 'COM FORA NT.006', 'SCORE', 'RISCO', 'DIAGNOSTICO'],
                array_map(static fn (ObraRisco $o): array => array_values($o->paraArray()), $obras),
                9, Paleta::RISCO, [2, 6], 'Nenhuma obra encontrada na base.'),
            self::bloco(
                'PEPs SEM MATERIAL BAIXADO NO PROJETO  (MAT PRJ CAD zerado ou vazio em todos os itens)   -   '
                    . ($rk?->totalSemBaixa('PEP3') ?? 0) . ' PEP3  |  ' . ($rk?->totalSemBaixa('PEP4') ?? 0) . ' PEP4',
                ['NIVEL', 'PEP', 'VALOR', 'SITUACAO', 'ITENS', 'MAT LIB SAP', 'TIPO OD', 'PEP3 DO PEP4', 'OBSERVACAO'],
                array_map(static fn (PepSemBaixa $p): array => array_values($p->paraArray()), $sem),
                0, Paleta::SEM_BAIXA, [2, 5], 'Todos os PEP3 e PEP4 tem ao menos um material baixado em MAT PRJ CAD.'),
        ], 'C00000', [7, 26, 14, 13, 9, 13, 14, 15, 8, 9, 70]);
    }

    private function diagnostico(ContextoAnalise $c, string $gerado): Aba
    {
        $d = $c->diagnostico;
        $sub = "Base: {$c->base->nome}  |  Gerado: {$gerado}  |  A nota mede a CONFIABILIDADE dos dados, nao o resultado das obras";
        $blocos = [];
        if ($d !== null) {
            $blocos[] = self::bloco('NOTA DE SAUDE', ['NOTA', 'SAUDE DOS DADOS'], [[
                $d->nota(),
                $d->rotulo() . '   (' . $d->criticos() . ' critico(s), ' . $d->atencoes() . ' atencao(oes))',
            ]]);
            $blocos[] = self::bloco('', ['VERIFICACAO', 'RESULTADO', 'STATUS', 'RECOMENDACAO'],
                array_map(static fn (Verificacao $v): array => array_values($v->paraArray()), $d->verificacoes),
                2, Paleta::VERIFICACAO);
        }

        return new Aba('DIAGNOSTICO BASE', "\u{2695}  DIAGNOSTICO DA BASE", $sub, $blocos, '00823E', [34, 44, 12, 80]);
    }

    private function devolucoes(ContextoAnalise $c): Aba
    {
        $dev = ResumidorPep3::devolucoes($c->resumo);

        return new Aba('DEVOLUCOES', 'DEVOLUCOES  -  PEP3 reprovados para envio a projetista', count($dev) . ' PEP3', [
            self::bloco('', ['PEP3NIVEL', 'SITUACAO', 'VALOR NAO ADER', 'FAMILIAS REPROVADAS', 'MOTIVO DEVOLUCAO'],
                array_map('array_values', $dev), moeda: [2], vazio: 'Nenhum PEP3 reprovado para exportar.'),
        ], 'C00000', [26, 13, 16, 40, 60]);
    }

    /**
     * @param list<string> $cab
     * @param list<list<mixed>> $linhas
     * @param array<string, array{0: string, 1: string}> $cores
     * @param list<int> $moeda
     */
    private static function bloco(
        string $titulo,
        array $cab,
        array $linhas,
        ?int $status = null,
        array $cores = [],
        array $moeda = [],
        string $vazio = '',
    ): Bloco {
        return new Bloco($titulo, $cab, $linhas, $status, $cores, $moeda, $vazio);
    }
}
