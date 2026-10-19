<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Ranking\CalculadoraRisco;

final class EtapaRanking implements Etapa
{
    public function nome(): string
    {
        return 'RANKING DE RISCO';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $ctx->ranking = (new CalculadoraRisco($ctx->parametros))
            ->calcular($ctx->sapPrj(), $ctx->alertas(), $ctx->precoUnitario, $ctx->racionalizacao);
    }
}
