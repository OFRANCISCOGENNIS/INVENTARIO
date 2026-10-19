<?php

declare(strict_types=1);

namespace Inventario\Application\UseCase;

use Inventario\Application\Carga\LeituraBases;
use Inventario\Application\Pipeline\ContextoAnalise;

final class ResultadoExecucao
{
    /**
     * @param list<ContextoAnalise> $analises
     * @param list<string> $erros bases que nao puderam ser analisadas
     */
    public function __construct(
        public readonly LeituraBases $leitura,
        public readonly array $analises,
        public readonly array $erros = [],
    ) {
    }
}
