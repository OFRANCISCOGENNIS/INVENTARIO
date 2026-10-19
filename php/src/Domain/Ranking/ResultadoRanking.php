<?php

declare(strict_types=1);

namespace Inventario\Domain\Ranking;

final class ResultadoRanking
{
    /**
     * @param list<ObraRisco> $obras
     * @param list<PepSemBaixa> $semBaixa PEP3 primeiro, depois PEP4
     */
    public function __construct(
        public readonly array $obras,
        public readonly array $semBaixa,
    ) {
    }

    public function totalSemBaixa(string $nivel): int
    {
        return count(array_filter($this->semBaixa, static fn (PepSemBaixa $p): bool => $p->nivel === $nivel));
    }
}
