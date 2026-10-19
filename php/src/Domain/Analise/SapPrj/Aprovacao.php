<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\SapPrj;

enum Aprovacao: string
{
    case APROVADO = 'APROVADO';
    case REPROVADO = 'REPROVADO';
    case SEM_UC = 'SEM UC';
}
