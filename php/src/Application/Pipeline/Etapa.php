<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline;

/**
 * Uma etapa da analise (uma aba do VBA). Falha numa etapa nao aborta as
 * outras: o Pipeline registra o erro e segue (erro granular).
 */
interface Etapa
{
    public function nome(): string;

    public function executar(ContextoAnalise $ctx): void;
}
