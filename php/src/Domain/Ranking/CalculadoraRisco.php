<?php

declare(strict_types=1);

namespace Inventario\Domain\Ranking;

use Inventario\Domain\Alerta\ResultadoAlertas;
use Inventario\Domain\Analise\Racionalizacao\LinhaRacionalizacao;
use Inventario\Domain\Analise\SapPrj\ResultadoSapPrj;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Preco\LinhaDiscrepanciaValor;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Ordenacao;

/**
 * RANKING DE RISCO POR OBRA: consolida reprovacao SAP x PRJ, alertas,
 * divergencias de preco, racionalizacao NT.006 e discrepancias de valor de
 * material (ODI) num SCORE 0-100 por obra
 * (ProcessarRankingRisco + SecaoSemBaixaPRJ do VBA).
 */
final class CalculadoraRisco
{
    public function __construct(private readonly Parametros $p)
    {
    }

    /**
     * @param list<LinhaPrecoUnitario> $precoUnitario
     * @param list<LinhaRacionalizacao> $racionalizacao
     * @param list<LinhaDiscrepanciaValor> $discrepancias
     */
    public function calcular(
        ResultadoSapPrj $analise,
        ResultadoAlertas $alertas,
        array $precoUnitario,
        array $racionalizacao,
        array $discrepancias = [],
    ): ResultadoRanking {
        $val = $rep = $al = $pu = $sob = $com = $dv = [];
        $p3Itn = $p3Bx = $p3Lib = $p4Itn = $p4Bx = $p4Lib = $p4Val = $p4P3 = [];

        foreach ($analise->linhas as $l) {
            $p3 = $l->pep3();
            if ($p3 === '') {
                continue;
            }
            $v = abs($l->valor());
            $val[$p3] = ($val[$p3] ?? 0.0) + $v;
            $rep[$p3] = ($rep[$p3] ?? false) || $l->reprovada();
            $baixado = $l->origem->num(Coluna::MAT_PRJ_CAD) != 0.0;
            $lib = abs($l->origem->num(Coluna::MAT_LIB_SAP));
            $p3Itn[$p3] = ($p3Itn[$p3] ?? 0) + 1;
            $p3Lib[$p3] = ($p3Lib[$p3] ?? 0.0) + $lib;
            if ($baixado) {
                $p3Bx[$p3] = ($p3Bx[$p3] ?? 0) + 1;
            }
            $p4 = $l->pep4();
            if ($p4 !== '') {
                $p4Itn[$p4] = ($p4Itn[$p4] ?? 0) + 1;
                $p4Lib[$p4] = ($p4Lib[$p4] ?? 0.0) + $lib;
                $p4Val[$p4] = ($p4Val[$p4] ?? 0.0) + $v;
                if ($baixado) {
                    $p4Bx[$p4] = ($p4Bx[$p4] ?? 0) + 1;
                }
                $p4P3[$p4] ??= $p3;
            }
        }
        foreach ($alertas->alertas() as $a) {
            $p3 = trim($a->pep3);
            if ($p3 !== '') {
                $al[$p3] = ($al[$p3] ?? 0) + 1;
                $val[$p3] ??= 0.0;
            }
        }
        foreach ($precoUnitario as $pl) {
            $p3 = trim($pl->pep3);
            if ($p3 === '' || !$pl->status->divergente()) {
                continue;
            }
            $pu[$p3] = ($pu[$p3] ?? 0) + 1;
            $val[$p3] ??= 0.0;
            $s = $pl->sobrepreco();
            if ($s > 0) {
                $sob[$p3] = ($sob[$p3] ?? 0.0) + $s;
            }
        }
        foreach ($racionalizacao as $r) {
            $p3 = trim($r->pep3);
            if ($p3 !== '' && $r->status->foraDoPrevisto()) {
                $com[$p3] = ($com[$p3] ?? 0) + 1;
                $val[$p3] ??= 0.0;
            }
        }

        foreach ($discrepancias as $d) {
            $p3 = trim($d->pep3);
            if ($p3 !== '') {
                $dv[$p3] = ($dv[$p3] ?? 0) + 1;
                $val[$p3] ??= 0.0;
            }
        }

        // score + ordenacao (maior score; empate -> maior valor de obra)
        $itens = [];
        foreach ($val as $p3 => $v) {
            $p3 = (string) $p3;
            $s = ($rep[$p3] ?? false) ? $this->p->numero('PESO_REPROV') : 0.0;
            $s += min(($al[$p3] ?? 0) * $this->p->numero('PESO_ALERTA'), $this->p->numero('CAP_ALERTA'));
            $s += min(($pu[$p3] ?? 0) * $this->p->numero('PESO_PU'), $this->p->numero('CAP_PU'));
            $s += min(($com[$p3] ?? 0) * $this->p->numero('PESO_COM'), $this->p->numero('CAP_COM'));
            $s += min(($dv[$p3] ?? 0) * $this->p->numero('PESO_DISCREP'), $this->p->numero('CAP_DISCREP'));
            $itens[] = ['p3' => $p3, 'score' => min($s, 100.0), 'valor' => $v];
        }
        $itens = Ordenacao::estavel($itens, static fn (array $a, array $b): int => [$b['score'], $b['valor']] <=> [$a['score'], $a['valor']]);

        $obras = [];
        foreach ($itens as $i => $it) {
            $p3 = $it['p3'];
            $nAl = $al[$p3] ?? 0;
            $nPu = $pu[$p3] ?? 0;
            $nCo = $com[$p3] ?? 0;
            $vSo = $sob[$p3] ?? 0.0;
            $nDv = $dv[$p3] ?? 0;
            $ehRep = $rep[$p3] ?? false;
            $diag = [];
            if ($ehRep) {
                $diag[] = 'REPROVADO na analise SAP x PRJ';
            }
            if ($nAl > 0) {
                $diag[] = "{$nAl} alerta(s) critico(s)";
            }
            if ($nPu > 0) {
                $diag[] = "{$nPu} diverg. de preco" . ($vSo > 0 ? ' (sobrepreco ' . Formatador::kpi($vSo) . ')' : '');
            }
            if ($nCo > 0) {
                $diag[] = "{$nCo} COM fora do previsto";
            }
            if ($nDv > 0) {
                $diag[] = "{$nDv} discrepancia(s) de valor de material";
            }
            if (isset($p3Itn[$p3]) && !isset($p3Bx[$p3])) {
                $diag[] = 'nenhum material baixado no PRJ';
            }
            $obras[] = new ObraRisco(
                $i + 1, $p3, $it['valor'], $ehRep, $nAl, $nPu, $vSo, $nCo, $it['score'],
                ObraRisco::nivel($it['score']),
                $diag === [] ? 'Sem apontamentos' : implode(' | ', $diag),
                $nDv,
            );
        }

        return new ResultadoRanking(
            $obras,
            $this->semBaixa($p3Itn, $p3Bx, $p3Lib, $val, $rep, $p4Itn, $p4Bx, $p4Lib, $p4Val, $p4P3),
        );
    }

    /**
     * PEP3 com todos os itens sem MAT PRJ CAD; PEP4 idem, listado so quando o
     * PEP3 dele TEM baixa em outro PEP4. Cada grupo por valor decrescente.
     *
     * @return list<PepSemBaixa>
     */
    private function semBaixa(
        array $p3Itn, array $p3Bx, array $p3Lib, array $p3Val, array $p3Rep,
        array $p4Itn, array $p4Bx, array $p4Lib, array $p4Val, array $p4P3,
    ): array {
        $nP4 = [];
        foreach ($p4P3 as $p3) {
            $nP4[$p3] = ($nP4[$p3] ?? 0) + 1;
        }
        $g3 = [];
        foreach ($p3Itn as $k => $n) {
            if (!isset($p3Bx[$k])) {
                $g3[] = ['pep' => (string) $k, 'valor' => $p3Val[$k] ?? 0.0];
            }
        }
        $g4 = [];
        foreach ($p4Itn as $k => $n) {
            if (!isset($p4Bx[$k]) && isset($p3Bx[$p4P3[$k]])) {
                $g4[] = ['pep' => (string) $k, 'valor' => $p4Val[$k]];
            }
        }
        $porValor = static fn (array $r): float => $r['valor'];
        $g3 = Ordenacao::selecaoDesc($g3, $porValor);
        $g4 = Ordenacao::selecaoDesc($g4, $porValor);

        $out = [];
        foreach ($g3 as $r) {
            $pep = $r['pep'];
            $itens = $p3Itn[$pep];
            $obs = "Nenhum dos {$itens} itens (em " . ($nP4[$pep] ?? 0) . ' PEP4) tem MAT PRJ CAD baixado';
            $out[] = new PepSemBaixa('PEP3', $pep, $r['valor'], self::situacao($p3Rep, $pep), $itens,
                $p3Lib[$pep], '', '', $obs . self::obsLib($p3Lib[$pep]));
        }
        foreach ($g4 as $r) {
            $pep = $r['pep'];
            $p3 = $p4P3[$pep];
            $itens = $p4Itn[$pep];
            $obs = "O PEP3 tem baixa em outros PEP4; este PEP4 nao tem nenhuma ({$itens} itens)";
            $out[] = new PepSemBaixa('PEP4', $pep, $r['valor'], self::situacao($p3Rep, $p3), $itens,
                $p4Lib[$pep], TipoOD::classificar($pep)->value, $p3, $obs . self::obsLib($p4Lib[$pep]));
        }

        return $out;
    }

    private static function situacao(array $rep, string $p3): ?string
    {
        return array_key_exists($p3, $rep) ? ($rep[$p3] ? 'REPROVADO' : 'APROVADO') : null;
    }

    /** SAP com material liberado e projeto sem nada baixado = o sinal mais forte. */
    private static function obsLib(float $lib): string
    {
        if ($lib <= 0) {
            return '';
        }
        $t = floor($lib) === $lib ? Formatador::inteiro($lib) : Formatador::decimal2($lib);

        return " | SAP liberou {$t} - material sem projeto";
    }
}
