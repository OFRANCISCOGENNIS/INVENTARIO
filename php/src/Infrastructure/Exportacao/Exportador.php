<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Exportacao;

use Inventario\Application\Relatorio\Relatorio;

interface Exportador
{
    public function extensao(): string;

    /** Grava o relatorio e devolve o caminho do arquivo. */
    public function exportar(Relatorio $r, string $caminho): string;
}
