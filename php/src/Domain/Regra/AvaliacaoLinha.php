<?php

declare(strict_types=1);

namespace Inventario\Domain\Regra;

/**
 * Resultado da avaliacao de uma linha SAP x PRJ (tAvalLinha do VBA): fonte
 * unica da regra, usada no veredito e na exibicao.
 */
final class AvaliacaoLinha
{
    public function __construct(
        public bool $ehAderente,
        public readonly bool $ucZerado,
        public readonly bool $avaliavel,
        public readonly bool $caboIsento,
        public bool $reprovaLinha,
        public readonly bool $recalculaSit,
        public readonly bool $violaOD,
        public readonly string $motivoOD,
    ) {
    }

    /**
     * Regra do MESMO numero para grupos de equivalentes: depois da avaliacao
     * normal, aderente so com total SAP igual ao total PRJ. UC zerado e
     * regras de OD prevalecem.
     */
    public function ajustarEquivalencia(bool $totaisIguais): void
    {
        if ($this->ucZerado || $this->violaOD) {
            return;
        }
        $this->ehAderente = $totaisIguais;
        $this->reprovaLinha = !$totaisIguais;
    }
}
