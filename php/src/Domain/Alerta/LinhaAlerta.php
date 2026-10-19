<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\LinhaBase;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Support\Texto;

/**
 * Linha da base ja preparada para as regras de alerta (campos lidos uma vez).
 */
final class LinhaAlerta
{
    public readonly string $familiaNorm;
    public readonly string $descricaoNorm;

    public function __construct(
        public readonly string $pep3,
        public readonly string $pep4,
        public readonly string $tipo,
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly string $familia,
        public readonly float $valor,
        public readonly float $qtd,
        public readonly TipoOD $tipoOD,
        public readonly string $tipoPepNorm = '',
    ) {
        $this->familiaNorm = Texto::norm($familia);
        $this->descricaoNorm = Texto::norm($descricao);
    }

    public static function de(LinhaBase $l): self
    {
        $pep4 = $l->texto(Coluna::PEP4);

        return new self(
            $l->texto(Coluna::PEP3),
            $pep4,
            $l->norm(Coluna::TIPO),
            $l->texto(Coluna::COD_MAT),
            $l->texto(Coluna::DESC_MAT),
            $l->texto(Coluna::FAMILIA),
            $l->num(Coluna::VALOR),
            $l->num(Coluna::MAT_LIB_SAP),
            TipoOD::classificar($pep4, $l->texto(Coluna::TIPO_PEP)),
            $l->norm(Coluna::TIPO_PEP),
        );
    }

    public function precoUnitario(): float
    {
        return $this->qtd == 0.0 ? 0.0 : $this->valor / $this->qtd;
    }

    /** Familia de condutor ou cabo (PU por metro, naturalmente baixo). */
    public function caboOuCondutor(): bool
    {
        return str_starts_with($this->familiaNorm, 'COND') || str_starts_with($this->familiaNorm, 'CABO');
    }
}
