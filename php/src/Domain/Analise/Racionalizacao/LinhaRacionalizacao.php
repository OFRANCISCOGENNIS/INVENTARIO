<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\Racionalizacao;

final class LinhaRacionalizacao
{
    public function __construct(
        public readonly string $pep4,
        public readonly string $pep3,
        public readonly string $codigo,
        public readonly string $material,
        public readonly string $familia,
        public readonly string $ligadoA,
        public readonly float $veio,
        public readonly string $previsto,
        public readonly StatusRacionalizacao $status,
        public readonly string $observacao,
    ) {
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'PEP4NIVEL' => $this->pep4,
            'PEP3NIVEL' => $this->pep3,
            'COD MATERIAL' => $this->codigo,
            'MATERIAL' => $this->material,
            'FAMILIA' => $this->familia,
            'LIGADO A (referencia)' => $this->ligadoA,
            'VEIO' => $this->veio,
            'PREVISTO' => $this->previsto,
            'STATUS' => $this->status->rotulo(),
            'OBSERVACAO' => $this->observacao,
        ];
    }
}
