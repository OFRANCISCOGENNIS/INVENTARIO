<?php

declare(strict_types=1);

namespace Inventario\Domain\Regra;

/**
 * Marca de parametro opcional nao informado (o IsMissing do VBA), diferente
 * de null, que e uma celula vazia.
 */
enum Ausente
{
    case VALOR;
}
