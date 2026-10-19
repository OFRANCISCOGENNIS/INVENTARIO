<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Historico\ComparadorHistorico;

/** HISTORICO: uma linha por obra por execucao (situacao e se mudou). */
final class EtapaHistorico implements Etapa
{
    public function nome(): string
    {
        return 'HISTORICO';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        if (!$ctx->usaHistorico() || $ctx->resumo === []) {
            return;
        }
        $repo = $ctx->repositorioHistorico;
        $ctx->historico = (new ComparadorHistorico())->comparar($ctx->resumo, $repo->situacoesAnteriores(), $ctx->agora);
        $repo->registrarObras($ctx->historico);
    }
}
