<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Analise\SapPrj\AnalisadorSapPrj;

final class EtapaAnaliseSapPrj implements Etapa
{
    public function nome(): string
    {
        return 'ANALISE SAP x PRJ';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        foreach ($ctx->equivalencias->log() as $msg) {
            $ctx->saude->log($msg);
        }
        $ctx->saude->log(sprintf('EQUIVALENCIAS: %d codigo(s) em %d grupo(s)',
            $ctx->equivalencias->totalCodigos(), $ctx->equivalencias->totalGrupos()));
        $ctx->sapPrj = (new AnalisadorSapPrj($ctx->parametros, $ctx->equivalencias))->analisar($ctx->base);
    }
}
