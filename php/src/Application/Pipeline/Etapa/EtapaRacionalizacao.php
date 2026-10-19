<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Analise\Racionalizacao\RacionalizadorCom;

final class EtapaRacionalizacao implements Etapa
{
    public function nome(): string
    {
        return 'RACIONALIZACAO COM';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $ctx->racionalizacao = (new RacionalizadorCom())->racionalizar($ctx->base);
    }
}
