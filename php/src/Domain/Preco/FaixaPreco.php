<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

/** Faixa MIN/MAX de preco unitario de um material (BASE DE PRECOS). */
final class FaixaPreco
{
    public function __construct(
        public readonly float $min,
        public readonly float $max,
        public readonly string $texto = '',
    ) {
    }

    /** Ponto medio (MIN+MAX)/2; sem MAX usa o MIN. */
    public function pontoMedio(): float
    {
        $max = $this->max <= 0 ? $this->min : $this->max;

        return ($this->min + $max) / 2;
    }
}
