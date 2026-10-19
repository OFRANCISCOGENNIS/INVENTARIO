<?php

declare(strict_types=1);

namespace Inventario\Application\Porta;

use Inventario\Domain\Config\Parametros;

interface RepositorioParametros
{
    public function carregar(): Parametros;

    public function salvar(Parametros $p): void;
}
