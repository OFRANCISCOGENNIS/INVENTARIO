<?php

declare(strict_types=1);

namespace Inventario\Domain\Ranking;

/**
 * PEP sem material baixado no projeto: todos os itens com MAT PRJ CAD zero
 * ou vazio.
 */
final class PepSemBaixa
{
    public function __construct(
        public readonly string $nivel,
        public readonly string $pep,
        public readonly float $valor,
        public readonly ?string $situacao,
        public readonly int $itens,
        public readonly float $matLibSap,
        public readonly string $tipoOD,
        public readonly string $pep3DoPep4,
        public readonly string $observacao,
    ) {
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'NIVEL' => $this->nivel,
            'PEP' => $this->pep,
            'VALOR' => $this->valor,
            'SITUACAO' => $this->situacao,
            'ITENS' => $this->itens,
            'MAT LIB SAP' => $this->matLibSap,
            'TIPO OD' => $this->tipoOD,
            'PEP3 DO PEP4' => $this->pep3DoPep4,
            'OBSERVACAO' => $this->observacao,
        ];
    }
}
