<?php

declare(strict_types=1);

namespace Inventario\Application\Porta;

use Inventario\Domain\Equivalencia\CatalogoEquivalencias;

interface RepositorioEquivalencias
{
    public function carregar(): CatalogoEquivalencias;
}
