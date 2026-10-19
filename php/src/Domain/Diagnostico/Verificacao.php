<?php

declare(strict_types=1);

namespace Inventario\Domain\Diagnostico;

final class Verificacao
{
    public function __construct(
        public readonly string $nome,
        public readonly string $resultado,
        public readonly StatusVerificacao $status,
        public readonly string $recomendacao,
    ) {
    }

    /** @return array<string, string> */
    public function paraArray(): array
    {
        return [
            'VERIFICACAO' => $this->nome,
            'RESULTADO' => $this->resultado,
            'STATUS' => $this->status->value,
            'RECOMENDACAO' => $this->recomendacao,
        ];
    }
}
