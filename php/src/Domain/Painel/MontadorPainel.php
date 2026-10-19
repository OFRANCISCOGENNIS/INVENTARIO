<?php

declare(strict_types=1);

namespace Inventario\Domain\Painel;

use Inventario\Domain\Alerta\ResultadoAlertas;
use Inventario\Domain\Analise\SapPrj\ResultadoSapPrj;
use Inventario\Domain\Historico\KpiExecucao;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Support\Ordenacao;

/**
 * Visao executiva (ProcessarPainelGestor do VBA).
 */
final class MontadorPainel
{
    /** @param list<LinhaPrecoUnitario> $precoUnitario */
    public function montar(
        ResultadoSapPrj $analise,
        ResultadoAlertas $alertas,
        array $precoUnitario,
        ?KpiExecucao $anterior,
    ): PainelGestor {
        $valTot = 0.0;
        $valRisk = 0.0;
        $p3 = [];
        foreach ($analise->linhas as $l) {
            $v = $l->valor();
            $valTot += $v;
            if ($l->reprovada()) {
                $valRisk += abs($v);
            }
            $k = $l->pep3();
            if ($k !== '') {
                $p3[$k] = ($p3[$k] ?? false) || $l->reprovada();
            }
        }
        $nPep3 = count($p3);
        $nRep = count(array_filter($p3));

        // alertas por tipo, sem as replicas QUALIDADE DA BASE do diagnostico
        $tipos = [];
        $nAl = 0;
        foreach ($alertas->alertas() as $a) {
            $t = trim($a->tipo);
            if ($t === '' || $a->ehQualidadeBase() || str_contains(strtoupper($t), 'NENHUM ALERTA')) {
                continue;
            }
            $nAl++;
            $tipos[$t] = ($tipos[$t] ?? 0) + 1;
        }
        $lista = [];
        foreach ($tipos as $t => $q) {
            $lista[] = ['tipo' => (string) $t, 'qtd' => $q, 'participacao' => $q / $nAl];
        }
        $lista = Ordenacao::estavel($lista, static fn (array $a, array $b): int => $b['qtd'] <=> $a['qtd']);

        $nDiv = 0;
        $sob = 0.0;
        foreach ($precoUnitario as $p) {
            if ($p->status->divergente()) {
                $nDiv++;
            }
            $sob += $p->sobrepreco();
        }

        $ant = static fn (string $campo): ?float => $anterior === null ? null : (float) $anterior->{$campo};

        return new PainelGestor(
            $valTot,
            $nPep3,
            $nPep3 - $nRep,
            $nRep,
            $nAl,
            $nDiv,
            $sob,
            $valRisk,
            $lista,
            [
                'peps' => Variacao::calcular($nPep3, $ant('peps'), false),
                'reprovados' => Variacao::calcular($nRep, $ant('reprovados'), true),
                'alertas' => Variacao::calcular($nAl, $ant('alertas'), true),
                'divergencias' => Variacao::calcular($nDiv, $ant('divergencias'), true),
            ],
        );
    }
}
