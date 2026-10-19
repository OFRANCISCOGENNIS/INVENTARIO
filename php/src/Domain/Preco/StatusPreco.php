<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

enum StatusPreco: string
{
    case DENTRO = 'DENTRO';
    case ABAIXO = 'ABAIXO DO MINIMO';
    case ACIMA = 'ACIMA DO MAXIMO';
    case SEM_REFERENCIA = 'SEM REFERENCIA';

    public function divergente(): bool
    {
        return $this === self::ABAIXO || $this === self::ACIMA;
    }
}
