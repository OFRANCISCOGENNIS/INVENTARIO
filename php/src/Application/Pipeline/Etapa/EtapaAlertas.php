<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Alerta\GeradorAlertas;
use Inventario\Domain\Preco\DiscrepanciaValor;
use Inventario\Domain\Preco\PrecosReferencia;
use Inventario\Domain\Preco\TabelaPrecos;

final class EtapaAlertas implements Etapa
{
    public function nome(): string
    {
        return 'ALERTA CRITICO';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        // precos de referencia so alimentam o UC SUBVALORIZADO: sem eles a etapa segue
        $ref = $ctx->tabelaPrecosReferencia !== []
            ? PrecosReferencia::deTabela($ctx->tabelaPrecosReferencia)
            : PrecosReferencia::dePontoMedio($ctx->tabelaPrecos ?? TabelaPrecos::semFonte('NENHUMA'));
        $ctx->saude->definirFonteReferencia($ref->fonte);

        // linhas ODI com PU fora da media do codigo: viram alerta aqui e a 3a secao do ranking
        $ctx->discrepanciaValor = (new DiscrepanciaValor($ctx->parametros->numero('DISCREP_VALOR_PCT')))
            ->analisar($ctx->base);
        $ctx->alertas = GeradorAlertas::padrao($ctx->parametros, $ref, $ctx->precoUnitario,
            DiscrepanciaValor::mediasOdi($ctx->base), $ctx->discrepanciaValor)->gerar($ctx->base);
    }
}
