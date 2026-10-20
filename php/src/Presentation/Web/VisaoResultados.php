<?php

declare(strict_types=1);

namespace Inventario\Presentation\Web;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Analise\SapPrj\Aprovacao;
use Inventario\Domain\Analise\SapPrj\LinhaAnalise;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Preco\LinhaDiscrepanciaValor;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Ranking\ObraRisco;
use Inventario\Domain\Ranking\PepSemBaixa;
use Inventario\Domain\Support\Numero;

/**
 * Converte uma analise no JSON da tela de Resultados: uma linha por item da
 * ANALISE SAP x PRJ com o status visual, os KPIs e os links de download.
 *
 * Status da tela (ordem de gravidade):
 *   div - Nao aderente: SIT MAT nao aderente
 *   sem - Sem correspondencia: so um dos lados (SAP ou PRJ) tem quantidade
 *   ver - Verificar: item aderente, mas a obra (PEP3) foi reprovada ou nao tem UC
 *   ok  - Aderente
 */
final class VisaoResultados
{
    /**
     * @param array<string, string> $downloads rotulo => url
     * @return array<string, mixed>
     */
    public function montar(ContextoAnalise $c, array $downloads, string $relatorioHtml): array
    {
        $faixas = self::faixasPorItem($c->precoUnitario);
        $temPrecos = $c->tabelaPrecos !== null && $c->tabelaPrecos->total() > 0;
        $varias = $c->base->temColuna(Coluna::ARQUIVO_ORIGEM);

        $linhas = [];
        foreach ($c->sapPrj()->linhas as $i => $l) {
            $linhas[] = $this->linha($i, $l, $faixas, $temPrecos, $varias ? $l->origem->texto(Coluna::ARQUIVO_ORIGEM) : $c->base->nome);
        }

        $p = $c->painel;

        return [
            'base' => $c->base->nome,
            'geradoEm' => $c->agora->format('d/m/Y') . ' às ' . $c->agora->format('H:i'),
            'selo' => $c->saude->selo(),
            'avisos' => array_merge(
                $c->saude->avisos(),
                array_map(static fn ($e) => "Falha na etapa {$e->etapa}: {$e->erro}",
                    array_values(array_filter($c->saude->etapas(), static fn ($e) => !$e->ok))),
            ),
            'temPrecos' => $temPrecos,
            'variasBases' => $varias,
            'obras' => ['total' => $p?->peps ?? 0, 'reprovadas' => $p?->reprovados ?? 0],
            'alertas' => $p?->alertas ?? 0,
            'notaBase' => $c->diagnostico?->nota(),
            'downloads' => $downloads,
            'linhas' => $linhas,
            'alertasLista' => array_map(static fn (Alerta $a): array => [
                'tipo' => $a->tipo,
                'pep3' => $a->pep3,
                'pep4' => $a->pep4,
                'code' => $a->codigo,
                'desc' => $a->descricao,
                'familia' => $a->familia,
                'valor' => $a->valor,
                'qtd' => $a->qtd,
                'pu' => $a->precoUnitario,
                'ref' => is_float($a->referencia) ? $a->referencia : ($a->referencia === null ? null : (string) $a->referencia),
                'motivo' => $a->motivo,
            ], $c->alertas()->alertas()),
            'ranking' => array_map(static fn (ObraRisco $o): array => [
                'pos' => $o->posicao,
                'pep3' => $o->pep3,
                'valor' => $o->valorObra,
                'reprovado' => $o->reprovado,
                'alertas' => $o->alertas,
                'divPreco' => $o->divergenciasPreco,
                'sobrepreco' => $o->sobrepreco,
                'comFora' => $o->comFora,
                'score' => $o->score,
                'risco' => $o->risco,
                'diag' => $o->diagnostico,
                'discrep' => $o->discrepanciasValor,
            ], $c->ranking?->obras ?? []),
            'discrepTol' => $c->parametros->numero('DISCREP_VALOR_PCT'),
            'discrepancias' => array_map(static fn (LinhaDiscrepanciaValor $d): array => [
                'pep3' => $d->pep3,
                'pep4' => $d->pep4,
                'code' => $d->codigo,
                'desc' => $d->descricao,
                'qtd' => $d->qtd,
                'valor' => $d->valor,
                'pu' => $d->precoUnitario,
                'media' => $d->media,
                'desvio' => $d->desvio,
                'n' => $d->lancamentos,
            ], $c->discrepanciaValor),
            'semBaixa' => array_map(static fn (PepSemBaixa $p): array => [
                'nivel' => $p->nivel,
                'pep' => $p->pep,
                'valor' => $p->valor,
                'obs' => $p->observacao,
            ], $c->ranking?->semBaixa ?? []),
            'relatorioHtml' => $relatorioHtml,
        ];
    }

    /**
     * @param array<string, array{0: string, 1: ?float, 2: ?float}> $faixas
     * @return array<string, mixed>
     */
    private function linha(int $i, LinhaAnalise $l, array $faixas, bool $temPrecos, string $base): array
    {
        $o = $l->origem;
        $qSap = self::qtd($o->bruto(Coluna::MAT_LIB_SAP));
        $qPrj = self::qtd($o->bruto(Coluna::MAT_PRJ_CAD));
        $valorSap = $o->num(Coluna::VALOR);
        $pu = $qSap !== null && $qSap != 0.0 ? $valorSap / $qSap : null;
        $dif = ($qPrj ?? 0.0) - ($qSap ?? 0.0);
        $cod = $o->codigo();
        $faixa = $faixas[$l->pep4() . '|' . $cod] ?? null;

        return [
            'id' => $i,
            'code' => $cod,
            'desc' => $o->texto(Coluna::DESC_MAT),
            'base' => $base,
            'pep3' => $l->pep3(),
            'pep4' => $l->pep4(),
            'tipo' => $o->texto(Coluna::TIPO),
            'familia' => $o->texto(Coluna::FAMILIA),
            'un' => $o->texto(Coluna::UND),
            'qSap' => $qSap,
            'qPrj' => $qPrj,
            'diff' => $dif,
            'price' => $pu,
            'valor' => $pu === null ? null : round($dif * $pu, 2),
            'valorSap' => $valorSap,
            'status' => self::status($l, $qSap, $qPrj),
            'sit' => $l->sitMat,
            'aprovacao' => $l->aprovacao->value,
            'motivo' => $l->motivo,
            'devolucao' => $l->motivoDevolucao,
            'priceFlag' => $temPrecos ? ($faixa === null ? null : $faixa[0]) : null,
            'mn' => $faixa[1] ?? null,
            'mx' => $faixa[2] ?? null,
        ];
    }

    private static function status(LinhaAnalise $l, ?float $qSap, ?float $qPrj): string
    {
        $temSap = $qSap !== null && $qSap != 0.0;
        $temPrj = $qPrj !== null && $qPrj != 0.0;
        if ($temSap !== $temPrj) {
            return 'sem';
        }
        if (str_contains(mb_strtoupper($l->sitMat, 'UTF-8'), 'NAO ADER')) {
            return 'div';
        }
        if ($l->aprovacao !== Aprovacao::APROVADO) {
            return 'ver';
        }

        return 'ok';
    }

    /** Quantidade da celula; null quando vazia ou com texto. */
    private static function qtd(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return Numero::converter($v);
    }

    /**
     * Situacao do preco unitario por PEP4 + codigo: "fora" quando alguma linha
     * do item ficou fora da faixa MIN/MAX.
     *
     * @param list<LinhaPrecoUnitario> $precos
     * @return array<string, array{0: string, 1: ?float, 2: ?float}>
     */
    private static function faixasPorItem(array $precos): array
    {
        $out = [];
        foreach ($precos as $p) {
            if ($p->faixa === null) {
                continue;
            }
            $k = $p->pep4 . '|' . $p->codigo;
            $fora = $p->status->divergente() || (($out[$k][0] ?? '') === 'fora');
            $out[$k] = [$fora ? 'fora' : 'dentro', $p->faixa->min, $p->faixa->max];
        }

        return $out;
    }
}
