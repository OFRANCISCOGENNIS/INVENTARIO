<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline;

final class RegistroEtapa
{
    public function __construct(
        public readonly string $etapa,
        public readonly bool $ok,
        public readonly float $segundos,
        public readonly string $erro = '',
    ) {
    }
}
