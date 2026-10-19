<?php

declare(strict_types=1);

namespace Inventario\Domain\Nt006;

/**
 * Referencia NT.006 de um material: familia, se e ancora e, quando depende
 * de uma ancora, a razao minima/maxima por unidade da ancora.
 */
final class MaterialNT006
{
    public const COD_POR_DESCRICAO = '~WEB';

    public function __construct(
        public readonly string $familia,
        public readonly string $codNT006,
        public readonly string $descricao,
        public readonly bool $ancora,
        public readonly string $ancoraDependente = '',
        public readonly float $razaoMin = 0.0,
        public readonly float $razaoMax = 0.0,
        public readonly string $regra = '',
    ) {
    }

    public function porDescricao(): bool
    {
        return $this->codNT006 === self::COD_POR_DESCRICAO;
    }
}
