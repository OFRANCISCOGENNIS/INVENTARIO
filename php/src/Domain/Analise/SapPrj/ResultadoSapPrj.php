<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\SapPrj;

/**
 * ANALISE SAP x PRJ: linhas e os KPIs do topo da aba.
 */
final class ResultadoSapPrj
{
    /** @param list<LinhaAnalise> $linhas */
    public function __construct(
        public readonly array $linhas,
        public readonly int $totalPep4,
        public readonly int $pep3Aprovados,
        public readonly int $pep3Reprovados,
        public readonly float $valorSap,
        public readonly float $valorNaoAderente,
    ) {
    }

    public function pctAprovados(): float
    {
        $d = $this->pep3Aprovados + $this->pep3Reprovados;

        return $d > 0 ? $this->pep3Aprovados / $d : 0.0;
    }

    public function pctReprovados(): float
    {
        $d = $this->pep3Aprovados + $this->pep3Reprovados;

        return $d > 0 ? $this->pep3Reprovados / $d : 0.0;
    }
}
