<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Historico\KpiExecucao;
use Inventario\Domain\Painel\MontadorPainel;

/**
 * PAINEL DO GESTOR. Le a rodada anterior ANTES de registrar esta (senao a
 * comparacao seria consigo mesma). Em lote nao compara: bases diferentes.
 */
final class EtapaPainel implements Etapa
{
    public function nome(): string
    {
        return 'PAINEL DO GESTOR';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $repo = $ctx->usaHistorico() ? $ctx->repositorioHistorico : null;
        $anterior = $repo?->ultimoKpi();
        $painel = (new MontadorPainel())->montar($ctx->sapPrj(), $ctx->alertas(), $ctx->precoUnitario, $anterior);
        $ctx->painel = $painel;
        $repo?->registrarKpi(new KpiExecucao(
            $ctx->agora,
            $painel->peps,
            $painel->reprovados,
            $painel->alertas,
            $painel->divergenciasPreco,
            $painel->valorEmRisco,
            $ctx->diagnostico?->nota() ?? 0.0,
        ));
    }
}
