<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Preco\DiscrepanciaValor;
use Inventario\Domain\Ranking\CalculadoraRisco;

final class EtapaRanking implements Etapa
{
    public function nome(): string
    {
        return 'RANKING DE RISCO';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $ctx->discrepanciaValor = (new DiscrepanciaValor($ctx->parametros->numero('DISCREP_VALOR_PCT')))
            ->analisar($ctx->base);
        $ctx->ranking = (new CalculadoraRisco($ctx->parametros))
            ->calcular($ctx->sapPrj(), $ctx->alertas(), $ctx->precoUnitario, $ctx->racionalizacao, $ctx->discrepanciaValor);
    }
}
