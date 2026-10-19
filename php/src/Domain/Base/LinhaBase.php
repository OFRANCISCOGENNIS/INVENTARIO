<?php

declare(strict_types=1);

namespace Inventario\Domain\Base;

use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * Uma linha da base, acessada pelo nome CANONICO da coluna. Coluna ausente
 * le como celula vazia (null).
 */
final class LinhaBase
{
    /**
     * @param array<string, mixed> $celulas nome canonico => valor bruto
     */
    public function __construct(
        public readonly int $numero,
        private readonly array $celulas,
    ) {
    }

    /** Valor bruto da celula (string, int, float, bool ou null). */
    public function bruto(string $coluna): mixed
    {
        return $this->celulas[$coluna] ?? null;
    }

    /** Trim(CStr(valor)) */
    public function texto(string $coluna): string
    {
        return Texto::limpo($this->bruto($coluna));
    }

    /** NormStr(CStr(valor)) */
    public function norm(string $coluna): string
    {
        return Texto::norm($this->bruto($coluna));
    }

    /** Val0(valor) */
    public function num(string $coluna): float
    {
        return Numero::val0($this->bruto($coluna));
    }

    public function codigo(): string
    {
        return Texto::codigo($this->bruto(Coluna::COD_MAT));
    }

    public function tipoOD(): TipoOD
    {
        return TipoOD::classificar($this->texto(Coluna::PEP4), $this->texto(Coluna::TIPO_PEP));
    }

    /** @return array<string, mixed> */
    public function celulas(): array
    {
        return $this->celulas;
    }
}
