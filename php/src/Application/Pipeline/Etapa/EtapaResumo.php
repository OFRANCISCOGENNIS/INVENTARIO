<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Analise\Resumo\ResumidorPep3;

final class EtapaResumo implements Etapa
{
    public function nome(): string
    {
        return 'RESUMO SAP x PRJ';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $ctx->resumo = (new ResumidorPep3())->resumir($ctx->sapPrj());
    }
}
